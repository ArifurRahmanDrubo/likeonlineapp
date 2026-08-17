<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Mail\OTPMail;
use App\Models\Customer;
use App\Models\CustomRole;
use App\Services\MailConfigService;
use Illuminate\Http\Request;
use App\Models\CustomPermission;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Customer portal registration.
     *
     * Registration is restricted to existing ISP subscribers ONLY:
     *   1. The customer must match an active record in `customers` by their
     *      ISP-provided PPPoE username + password.
     *   2. The customer must not be left/suspended.
     *   3. The customer must not already have a portal account
     *      (customers.user_id must be NULL).
     *
     * The customer row is locked (lockForUpdate) so two concurrent requests
     * can never both pass the "already registered" check and double-bind.
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'mobile' => 'required',
                'ispusername' => 'required|string',
                'isppassword' => 'required|string',
            ]);
            $ispusername = trim((string) $request->input('ispusername'));
            $isppassword = (string) $request->input('isppassword');

            // Lock the customer row for the whole verification + binding flow
            // so a parallel registration cannot slip past the user_id check.
            $customer = Customer::where('username', $ispusername)
                ->where('password', $isppassword)
                ->lockForUpdate()
                ->first();

            // 1. No matching ISP subscriber -> block registration.
            if (!$customer) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Invalid ISP credentials. Only active ISP subscribers are allowed to register.',
                ], 422);
            }

            // 2. Inactive / suspended subscriber -> block registration.
            if (in_array($customer->status, ['left', 'suspended'], true)) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Account is inactive or suspended. Please contact ISP support.',
                ], 403);
            }

            // 3. Already registered -> direct to login / password reset.
            if ($customer->user_id) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Account is already registered. Please proceed to Login or Reset Password.',
                ], 409);
            }

            // 4. Valid & unregistered -> create the portal account and bind it.
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'mobile' => $request->input('mobile'),
                'password' => Hash::make($request->password),
            ]);
            $customer->update([
                'user_id' => $user->id,
            ]);
            $role = CustomRole::firstOrCreate([
                'name' => 'client',
                'guard_name' => 'sanctum',
                'status' => 'Not Assigned',
                'created_by' => '', // Set created_by to the user ID
            ]);
            // Use firstOrCreate so repeated registrations reuse the same
            // permission rows (permissions.name is unique).
            $parent = CustomPermission::firstOrCreate(
                ['name' => 'client'],
                [
                    'guard_name' => 'web',
                    'module' => 'parent',
                    'type' => 'read', // Default type for top-level permissions
                    'parent_id' => null,
                    'created_by' => 'System',
                ]
            );
            $permission = CustomPermission::firstOrCreate(
                ['name' => 'client_profile'],
                [
                    'guard_name' => 'web',
                    'module' => 'child',
                    'type' => 'read', // Default type for top-level permissions
                    'parent_id' => $parent->id,
                    'created_by' => 'System',
                ]
            );
            $permission1 = CustomPermission::firstOrCreate(
                ['name' => 'new_request'],
                [
                    'guard_name' => 'web',
                    'module' => 'child',
                    'type' => 'read', // Default type for top-level permissions
                    'parent_id' => $parent->id,
                    'created_by' => 'System',
                ]
            );
            // Attach without detaching so existing client-role permissions are kept
            $role->permissions()->syncWithoutDetaching([$parent->id, $permission->id, $permission1->id]);

            $user->role()->associate($role);
            $user->save();

            DB::commit();

            return response()->json(['message' => 'User registered successfully!', 'status' => 'success']);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'fail', 'message' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Dedicated customer portal login.
     *
     * Authenticates via the portal account's email OR the ISP PPPoE username,
     * then verifies the account actually holds the `client` role and is bound
     * to a Customer record — non-client accounts cannot enter the portal.
     */
    public function customerLogin(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string',
                'password' => 'required|string',
            ]);

            $identifier = trim((string) $request->input('email'));

            // Resolve the user by email first, then by the ISP PPPoE username
            // (customers.username -> customers.user_id -> users).
            $user = User::where('email', $identifier)->first();
            if (!$user) {
                $customer = Customer::where('username', $identifier)
                    ->whereNotNull('user_id')
                    ->first();
                $user = $customer?->user;
            }

            if (!$user || !Hash::check((string) $request->input('password'), $user->password)) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'The provided credentials are incorrect.',
                ], 401);
            }

            // Portal access requires the client role AND a bound customer record.
            $isClient = $user->role && strtolower($user->role->name) === 'client';
            $hasCustomer = Customer::where('user_id', $user->id)->exists();
            if (!$isClient || !$hasCustomer) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'This account is not authorized to access the customer portal.',
                ], 403);
            }

            $token = $user->createToken('portal_token')->plainTextToken;

            // Same payload shape as the admin login so the SPA can hydrate its
            // Pinia auth store from a single response.
            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'message' => 'Login Successful',
                'status' => 'success',
                'user' => $user->load('profile'),
                'role' => $user->role->name,
                'permissions' => $user->role->permissions()->select('name', 'type', 'module')->get(),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'fail', 'message' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => 'An error occurred. Please try again.'], 500);
        }
    }


    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.',
                ]);
            }

            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json(['access_token' => $token, 'token_type' => 'Bearer', 'message' => ' Login Successful', 'status' => 'success']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->errors()], 422);
        } catch (AuthenticationException $e) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $request)
    {
        // Get the authenticated user
        // $user = $request->user();

        // // Delete the current access token
        // $user->currentAccessToken()->delete();

        // // Dispatch the Logout event
        // event(new Logout(auth()->guard('web'), $user));

        // // Return a successful response
        // return response()->json(['message' => 'Logout successful!']);
        $user = $request->user();

        // Check if the current token is a Personal Access Token
        if ($request->user()->currentAccessToken() instanceof PersonalAccessToken) {
            return response()->json(['message' => 'This is a Personal Access Token']);
        }

        // Otherwise, it's a Transient Token
        return response()->json(['message' => 'This is a Transient Token']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }


    function SendOTPCode(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|string|email|max:50'
            ]);

            $email = $request->input('email');
            $otp = rand(1000, 9999);
            $count = User::where('email', '=', $email)->count();

            if ($count == 1) {
                MailConfigService::apply();
                Mail::to($email)->send(new OTPMail($otp));
                User::where('email', '=', $email)->update(['otp' => $otp]);
                return response()->json(['status' => 'success', 'message' => '4 Digit OTP Code has been send to your email !']);
            } else {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Invalid Email Address'
                ]);
            }
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }

    function VerifyOTP(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email|max:50',
                'otp' => 'required|string|min:4'
            ]);

            $email = $request->input('email');
            $otp = $request->input('otp');

            $user = User::where('email', '=', $email)->where('otp', '=', $otp)->first();

            if (!$user) {
                return response()->json(['status' => 'fail', 'message' => 'Invalid OTP']);
            }

            // CurrentDate-UpdatedTe=4>Min

            User::where('email', '=', $email)->update(['otp' => '0']);

            $token = $user->createToken('authToken')->plainTextToken;
            // Include the user's profile so the SPA can render the topbar
            // without a secondary /api/user-profile call.
            $user->load('profile');
            return response()->json([
                'status' => 'success',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'message' => ' OTP Verification Successful',
                'user' => $user,
                'role' => $user->role ? $user->role->name : null,
                'permissions' => $user->role ? $user->role->permissions()->select('name', 'type', 'module')->get() : [],
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage()]);
        }
    }

    function ResetPassword(Request $request)
    {
        try {
            $request->validate([
                'password' => 'required|string|min:3'
            ]);
            $user = Auth::user();
            $userId = $user->id;
            $password = $request->input('password');
            User::where('id', '=', $userId)->update(['password' => Hash::make($password)]);
            return response()->json(['status' => 'success', 'message' => 'Request Successful']);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => $e->getMessage(),]);
        }
    }
    public function UpdatePassword(Request $request)
    {
        $request->validate([
            'currentPassword' => 'required',
            'newPassword' => 'required|min:8',
        ]);

        $user = Auth::user();
        $userId = $user->id;
        if (!Hash::check($request->currentPassword, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect']);
        }
        User::where('id', '=', $userId)->update(['password' => Hash::make($request->newPassword)]);

        return response()->json(['message' => 'Password updated successfully']);
    }


    public function logoutOtherUser(Request $request)
    {

        $userId = $request->input('user_id');
        // Find the user to log out
        $user = User::findOrFail($userId);

        // Revoke all tokens for the specified user
        $user->tokens->each(function ($token) {
            $token->delete();
        });
        event(new Logout(auth()->guard('web'), $user));
        return response()->json(['message' => 'User logged out successfully!']);
    }
}
