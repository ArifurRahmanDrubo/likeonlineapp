<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\CustomRole;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\CustomPermission;
use App\Models\UserLoginHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class RoleAndPermissionController extends Controller
{
    public function checkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $exists = User::where('email', $request->email)->exists();

        return response()->json(['exists' => $exists]);
    }
    public function getUserPermissions()
    {
        $user = Auth::user();
        $role = $user->role;
        $permissions = $role->permissions()->select('name', 'type', 'module')->get();

        return response()->json([
            'role' => $user->role->name,
            'permissions' => $permissions,
        ]);
    }
    public function getAllRoles()
    {
        $roles = CustomRole::all();

        $formattedroles = $roles->map(function ($role) {
            // Convert the model to an array and format the created_at field
            $roleArray = $role->toArray();
            $roleArray['created'] = $role->created_at->format('j M Y');

            return $roleArray;
        });

        return response()->json([

            'roles' => $formattedroles,

        ]);
    }
    public function getroleswithoutSuperAdmin()
    {
        $roles = CustomRole::where('name', '!=', 'super admin')->get();
        return response()->json([

            'roles' => $roles,

        ]);
    }
    public function getRolesWithoutPermission()
    {
        $roles = CustomRole::whereDoesntHave('permissions')
            ->where('name', '!=', 'super admin') // Assuming the role name is 'super admin'
            ->get();

        $formattedroles = $roles->map(function ($role) {
            // Convert the model to an array and format the created_at field
            $roleArray = $role->toArray();
            $roleArray['created'] = $role->created_at->format('j M Y');

            return $roleArray;
        });

        return response()->json([

            'roles' => $formattedroles,

        ]);
    }
    public function RoleAndPermission()
    {
        $user = Auth::user();

        return response()->json([]);
    }
    public function rolewithpermissions()
    {
        $roles = CustomRole::whereHas('permissions')
            ->with('permissions') // Eager load permissions
            ->get();
        $formattedroles = $roles->map(function ($role) {
            // Convert the model to an array and format the created_at field
            $roleArray = $role->toArray();
            $roleArray['created'] = $role->created_at->format('j M Y');

            return $roleArray;
        });

        return response()->json([
            'roleWithPermission' => $formattedroles,
        ]);
    }
    public function createRole(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|unique:roles,name',
                'status' => 'required|string'
            ]);
            $user = Auth::user();

            CustomRole::create([
                'name' => $request->input('name'),
                'status' => $request->input('status'),
                'created_by' => $user->role->name,
                'guard_name' => 'sanctum'
            ]);
            return response()->json([
                'message' => 'Role Created Successfull'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateRole(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required',
                'status' => 'required|string'
            ]);
            $id = $request->input('id');
            $role = CustomRole::find($id);
            $user = Auth::user();

            $role->update([
                'name' => $request->input('name'),
                'status' => $request->input('status'),
                'created_by' => $user->role->name,
            ]);
            return response()->json([
                'message' => 'Role Updated Successfull'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' =>  $e->getMessage(),
            ], 500);
        }
    }
    public function deleteRole(Request $request)
    {
        try {
            $id = $request->input('id');
            $role = CustomRole::findOrFail($id);

            // Check if the role is assigned to any users
            if ($role->users()->count() > 0) {
                return response()->json([
                    'message' => 'Role cannot be deleted because it is assigned to one or more users.'
                ], 200);
            }

            // Check if the role has any permissions
            if ($role->permissions()->count() > 0) {
                return response()->json([
                    'message' => 'Role cannot be deleted because it has permissions assigned.First Remove Permissions from the Permission.'
                ], 200);
            }

            // If no users and no permissions, delete the role
            $role->delete();

            return response()->json(['message' => 'Role deleted successfully.']);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createOrupdatePermission(Request $request)
    {
        // Log the incoming request data for debugging


        // Validate request
        $validated = $request->validate([
            'permissions' => 'required|json',
            'role_id' => 'required|exists:roles,id',
        ]);

        // Find the role
        $role = CustomRole::find($validated['role_id']);
        if (!$role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }
        $role->permissions()->detach();

        // Step 2: Optionally, delete permissions that are no longer associated with any role
        $permissions = CustomPermission::whereDoesntHave('roles')->get();

        // Delete the permissions
        foreach ($permissions as $permission) {
            $permission->delete();
        }

        // Get the user
        $user = Auth::user();

        // Decode the permissions data
        $permissionsData = json_decode($validated['permissions'], true);

        // Check if permissions data is an array
        if (!is_array($permissionsData)) {
            return response()->json(['message' => 'Invalid permissions data format.'], 400);
        }

        // Process permissions
        foreach ($permissionsData as $permission) {
            // Validate individual permission structure
            if (!isset($permission['value'])) {
                return response()->json(['message' => 'Invalid permission data.'], 400);
            }
            $permissionType = $permission['permission_type'] ?? 'full';
            // Create parent permission
            $parent = CustomPermission::create([
                'name' => $permission['value'],
                'guard_name' => 'web',
                'module' => 'parent',
                'type' => $permissionType, // Default type for top-level permissions
                'parent_id' => null,
                'created_by' => $user->name,
            ]);
            $role->permissions()->attach($parent->id);

            // Handle modules directly under the parent
            if (isset($permission['modules'])) {
                $this->storeModules($permission['modules'], $parent->id, $user, $role);
            }

            // Recursively store child permissions if they exist
            if (isset($permission['children'])) {
                $this->storeChildPermissions($permission['children'], $parent->id, $user, $role);
            }
        }

        return response()->json(['message' => 'Permissions stored successfully.'], 200);
    }

    private function storeChildPermissions(array $children, $parentId, $user, $role)
    {
        foreach ($children as $child) {
            $childType = $child['permission_type'] ?? 'full';
            $childPermission = CustomPermission::create([
                'name' => $child['value'],
                'guard_name' => 'web',
                'module' => 'child',
                'type' => $childType,
                'parent_id' => $parentId,
                'created_by' => $user->name,
            ]);
            $role->permissions()->attach($childPermission->id);
            // Handle child modules
            if (isset($child['childmodules'])) {
                $this->storeChildModules($child['childmodules'], $childPermission->id, $user, $role);
            }
        }
    }
    private function storeModules(array $modules, $parentId, $user, $role)
    {
        foreach ($modules as $module) {
            $moduleType = $module['permission_type'] ?? 'full';
            $modulePermission = CustomPermission::create([
                'name' => $module['value'],
                'guard_name' => 'web',
                'module' => 'module',
                'type' => $moduleType,
                'parent_id' => $parentId,
                'created_by' => $user->name,
            ]);
            $role->permissions()->attach($modulePermission->id);
        }
    }
    private function storeChildModules(array $childmodules, $childPermissionId, $user, $role)
    {
        foreach ($childmodules as $childmodule) {
            $childmoduleType = $childmodule['permission_type'] ?? 'full';
            $childmodulePermission = CustomPermission::create([
                'name' => $childmodule['value'],
                'guard_name' => 'web',
                'module' => 'childmodule',
                'type' => $childmoduleType, // Assuming 'module' type for modules
                'parent_id' => $childPermissionId,
                'created_by' => $user->name,
            ]);
            $role->permissions()->attach($childmodulePermission->id);
        }
    }


    public function updatePermission(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required',
                'status' => 'required|string'
            ]);
            $id = $request->input('id');
            $permission = CustomPermission::findOrFail($id);
            $user = Auth::user();

            $permission->update([
                'name' => $request->input('name'),
                'status' => $request->input('status'),
                'created_by' =>  $user->role->name,
            ]);
            return response()->json([
                'message' => ' Permission update Successfull'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' =>  $e->getMessage(),
            ], 500);
        }
    }
    public function deletePermission(Request $request)
    {
        try {
            // Validate the incoming request data
            $request->validate([
                'id' => 'required|exists:roles,id', // Ensure the ID exists in the roles table
            ]);

            $id = $request->input('id');
            $role = CustomRole::find($id);

            if ($role) {
                if ($role->users()->count() > 0) {
                    return response()->json([
                        'message' => 'Permission cannot be deleted because it is assigned to one or more users. First Delete Users'
                    ], 200);
                }
                // Step 1: Detach all permissions associated with this role
                $role->permissions()->detach();

                // Step 2: Optionally, delete permissions that are no longer associated with any role
                $permissions = CustomPermission::whereDoesntHave('roles')->get();

                // Delete the permissions
                foreach ($permissions as $permission) {
                    $permission->delete();
                }

                // Step 3: Optionally, delete the role itself
                // $role->delete();

                return response()->json(['message' => 'Permissions detached and deleted successfully.']);
            } else {
                return response()->json(['message' => 'Role not found.'], 404);
            }
        } catch (\Exception $e) {
            // Log the error message for debugging purposes
            // Log::error('Error deleting permission: ' . $e->getMessage());

            return response()->json([
                'message' => $e->getMessage() // Optionally return the error message for debugging
            ], 500);
        }
    }
    public function removePermission(Request $request)
    {

        $request->validate([
            'roleId' => 'required|integer|exists:roles,id',
            'permissionId' => 'required|integer|exists:permissions,id',
        ]);

        $roleId = $request->input('roleId');
        $permissionId = $request->input('permissionId');

        $role = CustomRole::find($roleId);

        if ($role) {
            $role->permissions()->detach($permissionId);
            return response()->json(['message' => 'Permission removed successfully.']);
        }

        return response()->json(['error' => 'User not found.'], 404);
    }

    public function createAppusers(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string',
                'status' => 'required|string',
                'role' => 'required|string|exists:roles,name', // Role must exist
            ]);
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'mobile' => $request->input('mobile'),
                'status' => $request->input('status'),
                'password' => Hash::make($request->input('password')),
            ]);

            $role = CustomRole::where('name', $request->input('role'))->first();
            if (!$role) {
                return response()->json(['message' => 'Role not found'], 404);
            }
            $user->role()->associate($role);
            $user->save();
            return response()->json([
                'message' => ' Users Created Successfull'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateAppusers(Request $request)
    {
        try {
            $id = $request->input('id');

            $user = User::find($id);
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $request->validate([
                'name' => 'required|string',
                'email' => [
                    'required',
                    'email',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'status' => 'required|string',
                'role' => 'required|string|exists:roles,name', // Role must exist
            ]);

            if ($request->input('password')) {
                $user = $user->update([
                    'name' => $request->input('name'),
                    'email' => $request->input('email'),
                    'mobile' => $request->input('mobile'),
                    'status' => $request->input('status'),
                    'password' => Hash::make($request->input('password')),
                ]);
            } else {
                $user = $user->update([
                    'name' => $request->input('name'),
                    'email' => $request->input('email'),
                    'mobile' => $request->input('mobile'),
                    'status' => $request->input('status'),
                ]);
            }

            $role = CustomRole::where('name', $request->input('role'))->first();
            if (!$role) {
                return response()->json(['message' => 'Role not found'], 404);
            }
            $user->role()->associate($role);
            return response()->json([
                'message' => ' Users Updated Successfull'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteAppusers(Request $request)
    {
        try {
            $id = $request->input('id');
            $user = User::findOrFail($id);
            $user->delete();
            return response()->json(['message' => 'User deleted successfully.']);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'An error occurred while Deleting  User'
            ], 500);
        }
    }
    public function getAllAppUsers()
    {
        // Retrieve all users with their roles and permissions
        $users = User::with('role.permissions')
            ->whereHas('role', function ($query) {
                $query->where('name', '!=', 'Super Admin');
            })
            ->get();
        return response()->json([
            'users' => $users,
        ]);
    }
    public function getAppUsers()
    {
        // Retrieve all users with their roles and permissions
        $users = User::all();
        return response()->json(
            $users,
        );
    }

    public function getAppUsersById(Request $request)
    {
        // Retrieve all users with their roles and permissions
        try {

            $id = $request->input('user_id');
            $user = User::where('id', $id)->with('role')->first();


            if ($user) {
                return response()->json([

                    'user' => $user,

                ]);
            } else {
                return response()->json([

                    'message' => 'user not found',

                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateAppuserPassword(Request $request)
    {
        // Retrieve all users with their roles and permissions
        try {
            $id = $request->input('user_id');
            $user = User::findOrFail($id);
            if ($user) {
                $user->update([
                    'password' => Hash::make($request->input('password')),
                ]);
                return response()->json([
                    'message' => 'Password Updated Successfully',
                ]);
            } else {
                return response()->json([
                    'message' => 'user not found',
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    // public function updateAppuserEmployee(Request $request)
    // {
    //     // Retrieve all users with their roles and permissions
    //     try {
    //         $id = $request->input('user_id');
    //         $user = User::findOrFail($id);
    //         if ($user) {
    //             $user->update([
    //                 'name' => $request->input('name'),
    //             ]);
    //             return response()->json([
    //                 'message' => 'Employee Assigned Successfully',
    //             ]);
    //         } else {
    //             return response()->json([
    //                 'message' => 'user not found',
    //             ]);
    //         }
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    public function updateAppuserInformation(Request $request)
    {
        // Retrieve all users with their roles and permissions
        try {
            $request->validate([
                'email' => 'required|email|unique:users,email',
                'status' => 'required|string',
            ]);
            $id = $request->input('user_id');
            $user = User::findOrFail($id);
            if ($user) {
                $user->update([
                    'email' => $request->input('email'),
                    'status' => $request->input('status'),
                ]);
                return response()->json([
                    'message' => 'Update User Information Successfully',
                ]);
            } else {
                return response()->json([
                    'message' => 'user not found',
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateAppuserRole(Request $request)
    {
        // Retrieve all users with their roles and permissions
        try {
            $request->validate([
                'role' => 'required|string|exists:roles,id', // Role must exist
            ]);
            $id = $request->input('user_id');
            $user = User::findOrFail($id);
            $role = CustomRole::findOrFail($id);
            if ($user) {
                $user->role()->delete;
                $user->role()->associate($role);
                return response()->json([
                    'message' => 'Role Assigned Successfully',
                ]);
            } else {
                return response()->json([
                    'message' => 'user not found',
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getbackOrginalPermissions(Request $request)
    {
        // Find the role by ID
        $roleId = $request->input('id');
        $role = CustomRole::find($roleId);
        if (!$role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }
        $permissions = $role->permissions()->orderBy('id')->get();
        return response()->json($permissions, 200);
    }

    public function loginHistory(Request $request)
    {
        // Optionally, add authentication and authorization checks here
        $userLoginHistories = UserLoginHistory::where('user_id', $request->user()->id)
            ->orderBy('logged_in_at', 'desc')
            ->get();

        return response()->json($userLoginHistories);
    }
}
