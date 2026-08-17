<?php

namespace App\Http\Controllers\Api\Portal;

use App\Mail\RegistrationOtpMail;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomPermission;
use App\Models\CustomRole;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            $customer->update(['user_id' => $user->id]);

            $this->attachClientRole($user);

            DB::commit();

            return response()->json(['message' => 'User registered successfully!', 'status' => 'success']);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'fail', 'message' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Step 1 of the two-step email-verified registration flow.
     *
     * POST /api/register/send-otp
     *
     * Validates the portal credentials (email + ISP username + ISP password +
     * portal password), verifies the ISP username + password match an active,
     * unregistered subscriber in `customers`, generates a 6-digit OTP and
     * stores the temporary registration payload in Cache for 10 minutes, then
     * emails the code.
     *
     * No account is created here — only the OTP is sent.
     */
    public function sendOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email|max:255',
                'username' => 'required|string',
                'isppassword' => 'required|string',
                'password' => 'required|string|min:8',
            ]);

            $email = strtolower(trim((string) $request->input('email')));
            $username = trim((string) $request->input('username'));
            $isppassword = (string) $request->input('isppassword');

            // The registration email must not already belong to any account
            // (users.email is unique and shared by staff + portal clients).
            if (User::where('email', $email)->exists()) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'This email is already registered. Please login or reset your password.',
                ], 409);
            }

            // A pending registration for the same email replaces the old one.
            if (Cache::has("reg_otp_{$email}")) {
                Cache::forget("reg_otp_{$email}");
            }

            // The ISP username + password must match an existing subscriber
            // (customers.password is the plaintext PPPoE secret used for
            // proof-of-knowledge verification).
            $customer = Customer::where('username', $username)
                ->where('password', $isppassword)
                ->first();
            if (!$customer) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Invalid ISP credentials. Only active ISP subscribers are allowed to register.',
                ], 422);
            }

            // Inactive / suspended subscriber -> block registration.
            if (in_array($customer->status, ['left', 'suspended'], true)) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Account is inactive or suspended. Please contact ISP support.',
                ], 403);
            }

            // Already registered -> direct to login / password reset.
            if ($customer->user_id) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Account is already registered. Please proceed to Login or Reset Password.',
                ], 409);
            }

            $otp = (string) random_int(100000, 999999);

            Cache::put("reg_otp_{$email}", [
                'code' => $otp,
                'username' => $username,
                'password' => Hash::make($request->input('password')),
            ], now()->addMinutes(10));

            Mail::to($email)->send(new RegistrationOtpMail($otp));

            return response()->json([
                'status' => 'success',
                'message' => 'A 6-digit verification code has been sent to your email. It is valid for 10 minutes.',
                'email' => $email,
                'expires_in' => 600,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'fail', 'message' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error("Portal registration OTP send failed: {$e->getMessage()}");
            return response()->json(['status' => 'fail', 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Step 2 of the two-step email-verified registration flow.
     *
     * POST /api/register/verify-otp
     *
     * Matches the submitted 6-digit code against the cached registration
     * payload. On success the customer account is created (name/mobile are
     * taken from the subscribers row, the hashed portal password from the
     * cache), bound to the customer and issued a Sanctum token so the SPA can
     * log the user straight into the customer portal.
     */
    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string|email|max:255',
                'otp' => 'required|string|digits:6',
            ]);

            $email = strtolower(trim((string) $request->input('email')));
            $otp = trim((string) $request->input('otp'));

            $payload = Cache::get("reg_otp_{$email}");

            // Constant-time comparison so a leaked timing side-channel cannot
            // help an attacker guess digits of the code.
            if (!$payload || !hash_equals((string) $payload['code'], $otp)) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Invalid or expired verification code. Please request a new one.',
                ], 422);
            }

            // Re-verify the subscriber while holding a row lock so a parallel
            // verify cannot double-bind the same customer.
            $customer = Customer::where('username', $payload['username'])
                ->lockForUpdate()
                ->first();

            if (!$customer) {
                Cache::forget("reg_otp_{$email}");
                return response()->json([
                    'status' => 'fail',
                    'message' => 'The ISP account no longer exists. Please contact ISP support.',
                ], 422);
            }

            if (in_array($customer->status, ['left', 'suspended'], true)) {
                Cache::forget("reg_otp_{$email}");
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Account is inactive or suspended. Please contact ISP support.',
                ], 403);
            }

            if ($customer->user_id) {
                Cache::forget("reg_otp_{$email}");
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Account is already registered. Please proceed to Login or Reset Password.',
                ], 409);
            }

            DB::beginTransaction();

            $user = User::create([
                'name' => $customer->name ?: $payload['username'],
                'email' => $email,
                // users.mobile is NOT nullable — fall back to an empty string
                // when the subscriber has no mobile on record.
                'mobile' => $customer->mobile ?: '',
                'password' => $payload['password'], // already hashed on send-otp
            ]);
            $customer->update(['user_id' => $user->id]);

            $this->attachClientRole($user);

            DB::commit();

            // The OTP payload is single-use.
            Cache::forget("reg_otp_{$email}");

            $token = $user->createToken('portal_token')->plainTextToken;

            // Same payload shape as the admin/portal login so the SPA can
            // hydrate its Pinia auth store from a single response.
            return response()->json([
                'status' => 'success',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'message' => 'Registration successful. Welcome to the customer portal!',
                'user' => $user->load('profile'),
                'role' => $user->role->name,
                'permissions' => $user->role->permissions()->select('name', 'type', 'module')->get(),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'fail', 'message' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error("Portal registration OTP verify failed: {$e->getMessage()}");
            return response()->json(['status' => 'fail', 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Attach the `client` role (with its portal permissions) to a freshly
     * created portal user. Shared by the legacy single-step register and the
     * new OTP-verified flow.
     */
    private function attachClientRole(User $user): void
    {
        $role = CustomRole::firstOrCreate([
            'name' => 'client',
            'guard_name' => 'sanctum',
            'status' => 'Not Assigned',
            'created_by' => '',
        ]);
        // Use firstOrCreate so repeated registrations reuse the same
        // permission rows (permissions.name is unique).
        $parent = CustomPermission::firstOrCreate(
            ['name' => 'client'],
            [
                'guard_name' => 'web',
                'module' => 'parent',
                'type' => 'read',
                'parent_id' => null,
                'created_by' => 'System',
            ]
        );
        $permission = CustomPermission::firstOrCreate(
            ['name' => 'client_profile'],
            [
                'guard_name' => 'web',
                'module' => 'child',
                'type' => 'read',
                'parent_id' => $parent->id,
                'created_by' => 'System',
            ]
        );
        $permission1 = CustomPermission::firstOrCreate(
            ['name' => 'new_request'],
            [
                'guard_name' => 'web',
                'module' => 'child',
                'type' => 'read',
                'parent_id' => $parent->id,
                'created_by' => 'System',
            ]
        );
        // Attach without detaching so existing client-role permissions are kept
        $role->permissions()->syncWithoutDetaching([$parent->id, $permission->id, $permission1->id]);

        $user->role()->associate($role);
        $user->save();
    }

    /**
     * Customer portal login.
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

    /**
     * GET /api/portal/me
     *
     * The authenticated portal user + their linked customer profile (used by
     * the layout for the topbar and Tawk.to attributes).
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            // Strict IDOR: the customer always comes from the session user's
            // own relation, never from request input.
            $customer = $user->customer()->with('server')->first();

            if (!$customer) {
                return response()->json([
                    'status' => 'fail',
                    'message' => 'No ISP subscription is linked to this account. Please contact ISP support.',
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'user' => $user->load('profile'),
                'customer' => [
                    'id' => $customer->id,
                    'radius_id' => $customer->radius_id,
                    'name' => $customer->name,
                    'username' => $customer->username,
                    'email' => $customer->email,
                    'mobile' => $customer->mobile,
                    'package_name' => $customer->package,
                    'package' => $customer->package,
                    'profile' => $customer->profile,
                    'status' => $customer->status ?: ($customer->billingstatus ? strtolower($customer->billingstatus) : 'unknown'),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'fail', 'message' => 'Failed to load profile.'], 500);
        }
    }
}
