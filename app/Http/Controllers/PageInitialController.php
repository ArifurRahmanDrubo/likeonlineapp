<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;

class PageInitialController extends Controller
{
    /**
     * Consolidated endpoint for initial page load data.
     * Returns lightweight users (excluding client role) and employees
     * in a single response payload with column selection.
     */
    public function getInitialUsersAndEmployees()
    {
        try {
            // Fetch users with only required columns, exclude client role
            $users = User::select('id', 'name', 'email', 'status')
                ->whereHas('role', function ($query) {
                    $query->where('name', '!=', 'client');
                })
                ->get();

            // Fetch employees with only required columns (name, id for dropdowns)
            $employees = Employee::select('id', 'name')->get();

            return response()->json([
                'users' => $users,
                'employees' => $employees,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch initial data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
