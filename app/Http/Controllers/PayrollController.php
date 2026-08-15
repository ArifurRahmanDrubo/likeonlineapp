<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Advance;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Models\CompanyProfile;
use App\Models\Generatedsallary;
use App\Services\PayrollService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    /**
     * Disburse a salary payment (partial or full) against the employee's
     * latest approved payslip and reduce the master ledger's due balance.
     *
     *   generatedsallary.paid_amount += amount_paid
     *   generatedsallary.due_amount  = total_salary - paid_amount
     *   payment_status               = paid | partial | unpaid
     *   payrolls.due_balance        -= amount_paid
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'paid_amount' => 'required|numeric|min:0.01',
                'payslip_id' => 'nullable|exists:generatedsallary,id',
            ]);

            $employeeId = (int) $request->input('employee_id');
            $amountPaid = round((float) $request->input('paid_amount'), 2);

            $payslip = $request->filled('payslip_id')
                ? Generatedsallary::find($request->input('payslip_id'))
                : Generatedsallary::where('employee_id', $employeeId)
                    ->where('approval_status', 'approved')
                    ->where('due_amount', '>', 0)
                    ->orderByDesc('sallary_month')
                    ->first();

            if (!$payslip) {
                return response()->json([
                    'error' => 'No approved payroll found for this employee. Approve the payroll first.',
                ], 422);
            }

            $dueAmount = (float) $payslip->due_amount;
            if ($amountPaid > $dueAmount) {
                return response()->json([
                    'error' => 'Amount paid exceeds the due amount (' . number_format($dueAmount, 2) . ').',
                ], 422);
            }

            $date = Carbon::parse($request->input('payment_date') ?: Carbon::now())->format('Y-m-d');

            DB::transaction(function () use ($request, $payslip, $employeeId, $amountPaid, $date) {
                $newPaid = round((float) $payslip->paid_amount + $amountPaid, 2);
                $newDue = round((float) $payslip->total_salary - $newPaid, 2);
                $paymentStatus = $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');

                $payslip->update([
                    'paid_amount' => $newPaid,
                    'due_amount' => max(0, $newDue),
                    'payment_status' => $paymentStatus,
                    'notes' => $request->input('notes'),
                ]);

                // Master ledger: paying salary reduces what we owe the employee.
                $ledger = Payroll::where('employee_id', $employeeId)->first();
                if ($ledger) {
                    $newBalance = round((float) $ledger->due_balance - $amountPaid, 2);
                    $ledger->update(['due_balance' => max(0, $newBalance)]);
                }

                Payslip::create([
                    'employee_id' => $employeeId,
                    'payment_amount' => $amountPaid,
                    'employee_code' => $request->input('employee_code'),
                    'payment_date' => $date,
                    'payment_by' => $request->input('payment_by'),
                    'transaction_no' => $request->input('transactionno'),
                    'notes' => $request->input('notes'),
                    'payment_info' => $request->input('paymentmethod'),
                ]);
            });

            return response()->json(['message' => 'Payment Successful']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing the data.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $employee = Payslip::where('employee_id', $request->input('id'))->latest()->first();

            if (!$employee) {
                return response()->json([
                    'error' => 'Employee not found.'
                ], 404);
            }
            return response()->json([
                'employee' => $employee
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'An error occurred while fetching the data.'
            ], 500);
        }
    }

    /**
     * List generated salary snapshots for an employee.
     */
    public function generatedSalary(Request $request)
    {
        try {
            $generatedSalary = Generatedsallary::where('employee_id', $request->input('id'))
                ->orderByDesc('payroll_date')
                ->get();

            return response()->json([
                'GeneratedSalary' => $generatedSalary,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a generated salary snapshot and reverse its ledger effects.
     */
    public function deleteGeneratedSalary(Request $request)
    {
        try {
            $generatedSalary = Generatedsallary::where('employee_id', $request->input('employee_id'))
                ->where('id', $request->input('id'))
                ->first();

            if (!$generatedSalary) {
                return response()->json(['message' => 'Generated salary record not found'], 404);
            }

            if ((float) $generatedSalary->paid_amount > 0) {
                return response()->json([
                    'error' => 'Payments have already been made against this salary; it cannot be deleted.',
                ], 422);
            }

            DB::transaction(function () use ($generatedSalary) {
                $ledger = Payroll::where('employee_id', $generatedSalary->employee_id)->first();

                // An approved payslip had its total added to the due balance.
                if ($ledger && $generatedSalary->approval_status === 'approved') {
                    $newBalance = round((float) $ledger->due_balance - (float) $generatedSalary->total_salary, 2);
                    $ledger->update(['due_balance' => max(0, $newBalance)]);
                }

                // The advance EMI deducted during generation is given back.
                if ($ledger && (float) $generatedSalary->advances > 0) {
                    $ledger->increment('advance_balance', (float) $generatedSalary->advances);
                }

                $generatedSalary->delete();
            });

            return response()->json(['message' => 'Generated salary record deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List payslip history for an employee.
     */
    public function payslipHistory(Request $request)
    {
        try {
            $payslips = Payslip::where('employee_id', $request->input('id'))
                ->orderByDesc('payment_date')
                ->get();

            return response()->json([
                'Payslip' => $payslips,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a payslip and restore the ledger + payslip balances.
     */
    public function deletePayslip(Request $request)
    {
        try {
            $payslip = Payslip::where('employee_id', $request->input('employee_id'))
                ->where('id', $request->input('id'))
                ->first();

            if (!$payslip) {
                return response()->json(['message' => 'Payslip not found'], 404);
            }

            DB::transaction(function () use ($payslip, $request) {
                $employeeId = $request->input('employee_id');
                $amount = round((float) $payslip->payment_amount, 2);

                // Find the generated salary this payment was made against (the
                // most recent one carrying a paid balance) and roll it back.
                $generated = Generatedsallary::where('employee_id', $employeeId)
                    ->where('paid_amount', '>', 0)
                    ->orderByDesc('sallary_month')
                    ->first();

                if ($generated) {
                    $newPaid = round((float) $generated->paid_amount - $amount, 2);
                    $newDue = round((float) $generated->total_salary - $newPaid, 2);
                    $generated->update([
                        'paid_amount' => max(0, $newPaid),
                        'due_amount' => max(0, $newDue),
                        'payment_status' => $newDue <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid'),
                    ]);
                }

                // The money goes back onto the master ledger's due balance.
                $ledger = Payroll::where('employee_id', $employeeId)->first();
                if ($ledger) {
                    $ledger->increment('due_balance', $amount);
                }

                $payslip->delete();
            });

            return response()->json(['message' => 'Employee payslip deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually trigger payroll generation for a given month/year.
     */
    public function generateManual(Request $request, PayrollService $payrollService)
    {
        try {
            $request->validate([
                'month' => 'required|integer|between:1,12',
                'year' => 'required|integer|min:2000|max:2100',
            ]);

            $results = $payrollService->generate((int) $request->input('year'), (int) $request->input('month'));

            // If the whole month was already generated, tell the caller.
            if ($results['generated'] === 0 && $results['skipped'] > 0) {
                return response()->json([
                    'error' => 'Payroll has already been generated for this month.',
                    'generated' => 0,
                    'skipped' => $results['skipped'],
                ], 422);
            }

            return response()->json([
                'message' => "Payroll generated: {$results['generated']} generated, {$results['skipped']} skipped.",
                'generated' => $results['generated'],
                'skipped' => $results['skipped'],
                'details' => $results['details'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while generating payroll.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a generated payslip — the net salary becomes owed on the ledger.
     */
    public function approve(Generatedsallary $payroll)
    {
        if ($payroll->approval_status === 'rejected') {
            return response()->json(['message' => 'Rejected payrolls cannot be approved. Generate a new payroll.'], 422);
        }

        if ($payroll->approval_status === 'approved') {
            return response()->json(['message' => 'Payroll is already approved.']);
        }

        DB::transaction(function () use ($payroll) {
            $payroll->update([
                'approval_status' => 'approved',
            ]);

            // Approved salary becomes owed to the employee.
            $ledger = Payroll::where('employee_id', $payroll->employee_id)->first();
            if ($ledger) {
                $ledger->increment('due_balance', (float) $payroll->total_salary);
            }
        });

        return response()->json(['message' => 'Payroll approved successfully.']);
    }

    /**
     * Reject a generated payslip.
     */
    public function reject(Generatedsallary $payroll)
    {
        if ($payroll->approval_status === 'approved' && $payroll->payment_status !== 'unpaid') {
            return response()->json(['message' => 'Payroll has payments and cannot be rejected.'], 422);
        }

        DB::transaction(function () use ($payroll) {
            // Reversing an earlier approval: the salary is no longer owed.
            if ($payroll->approval_status === 'approved') {
                $ledger = Payroll::where('employee_id', $payroll->employee_id)->first();
                if ($ledger) {
                    $newBalance = round((float) $ledger->due_balance - (float) $payroll->total_salary, 2);
                    $ledger->update(['due_balance' => max(0, $newBalance)]);
                }
            }

            $payroll->update([
                'approval_status' => 'rejected',
            ]);
        });

        return response()->json(['message' => 'Payroll rejected successfully.']);
    }

    /**
     * Bulk update the approval status of multiple payslips at once.
     *
     * Approving adds total_salary to the ledger due_balance; moving a payslip
     * away from approved reverses that addition so the ledger stays in sync.
     */
    public function bulkStatus(Request $request)
    {
        try {
            $request->validate([
                'payroll_ids' => 'required|array|min:1',
                'payroll_ids.*' => 'exists:generatedsallary,id',
                'status' => 'required|in:approved,rejected,pending_approval',
            ]);

            $payslips = Generatedsallary::whereIn('id', $request->input('payroll_ids'))->get();
            if ($payslips->isEmpty()) {
                return response()->json(['message' => 'No payrolls found for the given IDs.'], 404);
            }

            $status = $request->input('status');
            $updated = 0;

            DB::transaction(function () use ($payslips, $status, &$updated) {
                foreach ($payslips as $payslip) {
                    // Never reject a payslip that already has payments.
                    if ($status === 'rejected' && $payslip->payment_status !== 'unpaid') {
                        continue;
                    }

                    $ledger = Payroll::where('employee_id', $payslip->employee_id)->first();

                    if ($status === 'approved' && $payslip->approval_status !== 'approved' && $ledger) {
                        $ledger->increment('due_balance', (float) $payslip->total_salary);
                    } elseif ($status !== 'approved' && $payslip->approval_status === 'approved' && $ledger) {
                        $newBalance = round((float) $ledger->due_balance - (float) $payslip->total_salary, 2);
                        $ledger->update(['due_balance' => max(0, $newBalance)]);
                    }

                    $payslip->update(['approval_status' => $status]);
                    $updated++;
                }
            });

            return response()->json([
                'message' => "{$updated} payroll(s) marked as " . str_replace('_', ' ', $status) . '.',
                'updated' => $updated,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while updating payroll status.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a single payslip's approval status (approve / reject / pending).
     */
    public function updateStatus(Request $request, Generatedsallary $payroll)
    {
        try {
            $request->validate([
                'status' => 'required|in:approved,rejected,pending_approval',
            ]);

            $status = $request->input('status');

            if ($status === 'rejected' && $payroll->payment_status !== 'unpaid') {
                return response()->json([
                    'error' => 'Payroll has payments and cannot be rejected.',
                ], 422);
            }

            DB::transaction(function () use ($payroll, $status) {
                $ledger = Payroll::where('employee_id', $payroll->employee_id)->first();

                if ($status === 'approved' && $payroll->approval_status !== 'approved' && $ledger) {
                    $ledger->increment('due_balance', (float) $payroll->total_salary);
                } elseif ($status !== 'approved' && $payroll->approval_status === 'approved' && $ledger) {
                    $newBalance = round((float) $ledger->due_balance - (float) $payroll->total_salary, 2);
                    $ledger->update(['due_balance' => max(0, $newBalance)]);
                }

                $payroll->update(['approval_status' => $status]);
            });

            return response()->json([
                'message' => 'Payroll status updated successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while updating payroll status.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * KPI summary for the payroll dashboard (expense, advances, pending).
     */
    public function summary()
    {
        try {
            $payslips = Generatedsallary::all();

            return response()->json([
                'total_payroll_expense' => (float) $payslips->where('approval_status', 'approved')->sum('total_salary'),
                'pending_payslips' => $payslips->where('payment_status', '!=', 'paid')->count(),
                'total_advances_collected' => (float) Advance::sum('paid_amount'),
                'approval_counts' => [
                    'pending_approval' => $payslips->where('approval_status', 'pending_approval')->count(),
                    'approved' => $payslips->where('approval_status', 'approved')->count(),
                    'rejected' => $payslips->where('approval_status', 'rejected')->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching the summary.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function detailspayslip(Request $request)
    {
        try {
            $employee_id = $request->input('id');
            $companyProfile = CompanyProfile::all();
            $details = Employee::where('id', $employee_id)->with(['payroll', 'latestPayslip'])->first();

            if (!$details) {
                return response()->json([
                    'error' => 'Employee not found.'
                ], 404);
            }
            return response()->json([
                'Details' => $details,
                'companyProfile' => $companyProfile,
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'An error occurred while fetching the data.'
            ], 500);
        }
    }
}
