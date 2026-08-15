<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Advance;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvanceController extends Controller
{
    /**
     * Record a salary advance for an employee.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'total_amount' => 'required|numeric|min:0',
                'monthly_installment' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            DB::transaction(function () use ($validated, $request) {
                Advance::create([
                    'employee_id' => $validated['employee_id'],
                    // amount is kept for backward compatibility with reports
                    'amount' => $validated['total_amount'],
                    'total_amount' => $validated['total_amount'],
                    'monthly_installment' => $validated['monthly_installment'],
                    'paid_amount' => 0,
                    'remaining_amount' => $validated['total_amount'],
                    'status' => 'active',
                    'date' => Carbon::parse($request->input('date'))->format('Y-m-d'),
                    'notes' => $request->input('notes'),
                ]);

                // The outstanding advance balance on the employee's master
                // ledger grows by the full advance amount.
                Payroll::firstOrCreate(
                    ['employee_id' => $validated['employee_id']],
                    [
                        'basic_salary' => 0,
                        'advance_balance' => 0,
                        'due_balance' => 0,
                        'status' => 'active',
                    ]
                )->increment('advance_balance', (float) $validated['total_amount']);
            });

            return response()->json(['message' => 'Employee advance assigned successfully'], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List advances for an employee.
     */
    public function index(Request $request)
    {
        try {
            $advance = Advance::where('employee_id', $request->input('id'))
                ->orderByDesc('date')
                ->get();

            return response()->json([
                'Advance' => $advance,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an advance record.
     */
    public function destroy(Request $request)
    {
        try {
            $id = $request->input('id');
            $employee_id = $request->input('employee_id');

            $advance = Advance::where('employee_id', $employee_id)->where('id', $id)->first();
            if (!$advance) {
                return response()->json(['message' => 'Employee advance not found'], 404);
            }

            $advance->delete();

            return response()->json(['message' => 'Employee advance deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
