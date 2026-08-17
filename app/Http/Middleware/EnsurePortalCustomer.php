<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Restrict routes to customer-portal accounts.
 *
 * The authenticated user must hold the `client` role AND be bound to a
 * Customer record (customers.user_id) — otherwise 403. Portal controllers
 * then resolve the customer themselves via Auth::user()->customer, so a
 * customer-supplied ID is never trusted (no IDOR).
 */
class EnsurePortalCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  string  $role  Expected role (defaults to 'client'). Used as
     *                        `role:client` middleware on portal routes.
     */
    public function handle(Request $request, Closure $next, string $role = 'client')
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $roleName = $user->role ? strtolower($user->role->name) : null;
        if ($roleName !== strtolower($role)) {
            return response()->json([
                'status' => 'fail',
                'message' => 'This account is not authorized to access the customer portal.',
            ], 403);
        }

        $customer = Customer::where('user_id', $user->id)->first();
        if (!$customer) {
            return response()->json([
                'status' => 'fail',
                'message' => 'No ISP subscription is linked to this account. Please contact ISP support.',
            ], 403);
        }

        // Attach the resolved customer so controllers can scope every query.
        $request->attributes->set('portal_customer', $customer);

        return $next($request);
    }
}
