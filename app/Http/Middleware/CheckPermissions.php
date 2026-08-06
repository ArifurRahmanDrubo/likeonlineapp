<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class CheckPermissions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $permission
     * @param  string|null  $type
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permission = null, $type = 'read')
    {
        $user = Auth::user();

        // Check if the user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if the user is a super admin
        if ($user->role && $user->role->name === 'super admin') {
            return $next($request);
        }

        // Check if the user has the required permission with the correct type
        if ($user->role && $user->role->permissions->contains(function ($perm) use ($permission, $type) {
            return $perm->name === $permission && $this->checkPermissionType($perm->type, $type);
        })) {
            return $next($request);
        }

        // If not, deny access
        return response()->json(['message' => 'Access denied. Insufficient permissions.'], 403);
    }

    /**
     * Check if the permission type is valid for the requested type.
     *
     * @param  string  $permType
     * @param  string  $type
     * @return bool
     */
    protected function checkPermissionType($permType, $type)
    {
        // Implement the logic to match permission type
        if ($permType === 'full') {
            return true;
        }
        if ($permType === 'write' && ($type === 'write' || $type === 'read')) {
            return true;
        }
        if ($permType === 'read' && $type === 'read') {
            return true;
        }
        return false;
    }
}
