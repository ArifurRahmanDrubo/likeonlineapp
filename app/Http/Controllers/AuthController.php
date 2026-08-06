<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Mail\OTPMail;
use App\Models\Customer;
use App\Models\CustomRole;
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
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'mobile' => 'required',
            ]);
            $ispusername = $request->input('ispusername');
            $isppassword = $request->input('isppassword');

            $customer = Customer::where('username', $ispusername)->where('password', $isppassword)->first();
            if ($customer) {
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
                $parent = CustomPermission::create([
                    'name' => 'client',
                    'guard_name' => 'web',
                    'module' => 'parent',
                    'type' => 'read', // Default type for top-level permissions
                    'parent_id' => null,
                    'created_by' => 'System',
                ]);
                $role->permissions()->attach($parent->id);
                $permission = CustomPermission::create([
                    'name' => 'client_profile',
                    'guard_name' => 'web',
                    'module' => 'child',
                    'type' => 'read', // Default type for top-level permissions
                    'parent_id' => $parent->id,
                    'created_by' => 'System',
                ]);
                $permission1 = CustomPermission::create([
                    'name' => 'new_request',
                    'guard_name' => 'web',
                    'module' => 'child',
                    'type' => 'read', // Default type for top-level permissions
                    'parent_id' => $parent->id,
                    'created_by' => 'System',
                ]);
                $role->permissions()->attach($permission->id);
                $role->permissions()->attach($permission1->id);

                $user->role()->associate($role);
                $user->save();

                DB::commit();

                return response()->json(['message' => 'User registered successfully!']);
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Please Enter Your ISP Provided username & password'], 201);
            }
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
            return response()->json(['status' => 'success', 'access_token' => $token, 'token_type' => 'Bearer', 'message' => ' OTP Verification Successful']);
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
