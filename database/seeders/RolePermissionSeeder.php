<?php

namespace Database\Seeders;

use Exception;
use App\Models\User;
use App\Models\CustomRole;
use App\Models\CustomPermission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure there's at least one user to use as 'created_by'
        $user = User::first();

        if (!$user) {
            throw new Exception('No users found.');
        }

        // Create or get the role
        $role = CustomRole::firstOrCreate([
            'name' => 'super admin',
            'guard_name' => 'sanctum',
            'status' => 'Not Assigned',
            'created_by' => '', // Set created_by to the user ID
        ]);
        // Assign the role to the user
        $user->role()->associate($role); // If the relation is 'role' in User model
        $user->save();
    }
}
