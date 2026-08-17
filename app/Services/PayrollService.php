<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Advance;
use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Allowance;
use App\Models\LateFee;
use App\Models\Overtime;
use App\Models\Attendance;
use App\Models\HrSetting;
use App\Models\Generatedsallary;
use App\Models\SystemPermission;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Generate payroll for a given month/year for every employee.
     *
     * The engine pulls the employee's attendance log for the month and derives
     * the deductions/additions from it plus the HR settings:
     *
     *   daily_rate       = basic_salary / days_in_month
     *   absent_deduction = (total_absent + total_unpaid_leaves) * daily_rate
     *   late_fees        = per hr_settings late_fee_mode (salary_based,
     *                      fixed_per_late, fixed_per_minute) after late_grace_days
     *   overtime_amount  = total_ot_hours * hourly_rate, where hourly_rate is
     *                      (basic_salary / 208) * multiplier for salary_based or
     *                      the fixed rate for fixed_rate mode
     *
     *   net_salary = (basic_salary + allowances + overtime_amount)
     *              - (late_fees + advance_installment + absent_deduction)
     *
     * The result is a monthly payslip in `generatedsallary` linked to the
     * employee's master ledger (`payrolls`). The advance EMI is deducted from
     * the ledger's `advance_balance`; `due_balance` only grows on approval.
     *
     * Manual LateFee / Overtime table entries are added on top of the
     * attendance-derived values so legacy manual adjustments keep counting.
     *
     * @return array{generated:int, skipped:int, details:array<int,array<string,mixed>>}
     */
    public function generate(int $year, int $month): array
    {
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $startDate = $monthStart->format('Y-m-d');
        $endDate = $monthStart->copy()->endOfMonth()->format('Y-m-d');
        $salaryMonth = $monthStart->format('Y-m-d');

        $settings = $this->hrSettings();

        // System-permission toggles (system_permissions). These gate which
        // salary components are calculated on BOTH manual generation and the
        // payroll:generate cron — both funnel through this service.
        $system = SystemPermission::first();
        $withLateFees = $system ? $system->isEnabled('payroll_with_late_fees', true) : true;
        $withOvertime = $system ? $system->isEnabled('payroll_with_overtime', true) : true;
        $withAbsence = $system ? $system->isEnabled('payroll_with_absence', true) : true;

        $results = ['generated' => 0, 'skipped' => 0, 'details' => []];

        DB::transaction(function () use ($year, $month, $monthStart, $startDate, $endDate, $salaryMonth, $settings, $withLateFees, $withOvertime, $withAbsence, &$results) {
            foreach (Employee::all() as $employee) {
                // A payslip can only be generated once per (employee, month).
                $alreadyGenerated = Generatedsallary::where('employee_id', $employee->id)
                    ->where('sallary_month', $salaryMonth)
                    ->exists();

                if ($alreadyGenerated) {
                    $results['skipped']++;
                    $results['details'][] = ['employee_id' => $employee->id, 'name' => $employee->name, 'status' => 'skipped'];
                    continue;
                }

                // Every employee must have a 1-to-1 master ledger row.
                $ledger = Payroll::firstOrCreate(
                    ['employee_id' => $employee->id],
                    [
                        'basic_salary' => $employee->basic_salary ?? 0,
                        'advance_balance' => 0,
                        'due_balance' => 0,
                        'status' => 'active',
                    ]
                );

                $basicSalary = (float) ($employee->basic_salary ?? 0);
                $allowances = $this->totalAllowances($employee->id, $startDate, $endDate);

                $attendance = $this->attendanceSummary($employee->id, $startDate, $endDate);

                // Absence deduction only when payroll_with_absence is enabled
                // (otherwise the component is zeroed).
                $absentDeduction = $withAbsence
                    ? $this->absentDeduction($employee, $startDate, $endDate)
                    : 0.0;
                // Late-fee component (attendance-derived + manual) only when
                // payroll_with_late_fees is enabled.
                $lateFees = $withLateFees
                    ? $this->lateFeesFromAttendance($employee->id, $startDate, $endDate, $basicSalary, $settings)
                        + $this->totalLateFees($employee->id, $startDate, $endDate)
                    : 0.0;
                // Overtime component (attendance-derived + manual) only when
                // payroll_with_overtime is enabled.
                $overtime = $withOvertime
                    ? $this->overtimeFromAttendance($employee->id, $startDate, $endDate, $basicSalary, $settings)
                        + $this->totalOvertime($employee->id, $startDate, $endDate)
                    : 0.0;

                // Deduct the monthly EMI installment of every active advance
                // until each advance's remaining amount reaches 0.
                $advanceInstallment = $this->applyAdvanceInstallments($employee->id);

                $totalSalary = ($basicSalary + $allowances + $overtime) - ($lateFees + $advanceInstallment + $absentDeduction);
                $totalSalary = max(0, round($totalSalary, 2));

                // Monthly payslip snapshot (approval/payment start fresh).
                Generatedsallary::create([
                    'employee_id' => $employee->id,
                    'payroll_id' => $ledger->id,
                    'approval_status' => 'pending_approval',
                    'payment_status' => 'unpaid',
                    'paid_amount' => 0,
                    'due_amount' => $totalSalary,
                    'sallary_month' => $salaryMonth,
                    'payroll_date' => $endDate,
                    'basic_salary' => $basicSalary,
                    'allowances' => $allowances,
                    'late_fees' => round($lateFees, 2),
                    'overtime' => round($overtime, 2),
                    'advances' => $advanceInstallment,
                    'absent_deduction' => $absentDeduction,
                    'total_salary' => $totalSalary,
                ]);

                // Advance EMI collected this month reduces the outstanding
                // advance balance on the master ledger.
                if ($advanceInstallment > 0) {
                    $ledger->decrement('advance_balance', $advanceInstallment);
                    $ledger->refresh();
                    if ((float) $ledger->advance_balance < 0) {
                        $ledger->update(['advance_balance' => 0]);
                    }
                }

                $results['generated']++;
                $results['details'][] = [
                    'employee_id' => $employee->id,
                    'name' => $employee->name,
                    'status' => 'generated',
                    'total_salary' => $totalSalary,
                    'basic_salary' => $basicSalary,
                    'allowances' => $allowances,
                    'absent_deduction' => $absentDeduction,
                    'late_fees' => round($lateFees, 2),
                    'overtime_amount' => round($overtime, 2),
                    'advance_installment' => $advanceInstallment,
                    'attendance' => $attendance,
                ];
            }
        });

        return $results;
    }

    /**
     * Aggregate the attendance log of an employee for a month.
     *
     * @return array<string,int|float>
     */
    private function attendanceSummary(int $employeeId, string $startDate, string $endDate): array
    {
        $query = fn () => Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate]);

        return [
            'total_present' => (int) (clone $query())->whereIn('status', ['present', 'late'])->count(),
            'total_absent' => (int) (clone $query())->where('status', 'absent')->count(),
            'total_paid_leaves' => (int) (clone $query())->where('status', 'paid_leave')->count(),
            'total_unpaid_leaves' => (int) (clone $query())->where('status', 'unpaid_leave')->count(),
            'total_late_minutes' => (float) (clone $query())->where('status', 'late')->sum('late_minutes'),
            'total_ot_hours' => (float) $query()->sum('overtime_hours'),
        ];
    }

    /**
     * Apply the monthly EMI installment of every active advance and return
     * the total amount to deduct from this month's payroll.
     */
    private function applyAdvanceInstallments(int $employeeId): float
    {
        $totalInstallment = 0.0;

        $advances = Advance::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->get();

        foreach ($advances as $advance) {
            $remaining = (float) $advance->remaining_amount;
            $installment = (float) ($advance->monthly_installment ?? $remaining);
            $apply = min($installment, $remaining);

            $totalInstallment += $apply;

            $advance->paid_amount = round((float) $advance->paid_amount + $apply, 2);
            $advance->remaining_amount = round($remaining - $apply, 2);
            if ($advance->remaining_amount <= 0) {
                $advance->remaining_amount = 0;
                $advance->status = 'paid';
            }
            $advance->save();
        }

        return round($totalInstallment, 2);
    }

    private function totalAllowances(int $employeeId, string $startDate, string $endDate): float
    {
        return (float) Allowance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
    }

    private function totalLateFees(int $employeeId, string $startDate, string $endDate): float
    {
        return (float) LateFee::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('total_amount');
    }

    private function totalOvertime(int $employeeId, string $startDate, string $endDate): float
    {
        return (float) Overtime::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('total_amount');
    }

    /**
     * Deduct a daily salary for every absent or unpaid-leave day.
     */
    private function absentDeduction(Employee $employee, string $startDate, string $endDate): float
    {
        $basicSalary = (float) ($employee->basic_salary ?? 0);
        $daysInMonth = Carbon::parse($startDate)->daysInMonth;
        if ($daysInMonth <= 0) {
            return 0;
        }

        $dailySalary = $basicSalary / $daysInMonth;
        $unpaidDays = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['absent', 'unpaid_leave'])
            ->count();

        return round($dailySalary * $unpaidDays, 2);
    }

    /**
     * Late fee from the attendance log per the HR late-fee policy.
     *
     * The first `late_grace_days` late days of the month are free; penalties
     * apply from there on:
     *   - salary_based     : penalized late days x daily rate
     *   - fixed_per_late   : penalized late days x fixed amount
     *   - fixed_per_minute : total late minutes x fixed amount
     */
    private function lateFeesFromAttendance(int $employeeId, string $startDate, string $endDate, float $basicSalary, array $settings): float
    {
        $mode = $settings['late_fee_mode'] ?? 'salary_based';
        $graceDays = max(0, (int) ($settings['late_grace_days'] ?? 3));
        $fixedAmount = (float) ($settings['late_fee_fixed_amount'] ?? 0);

        $lateDays = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'late')
            ->count();
        $lateMinutes = (float) Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'late')
            ->sum('late_minutes');

        $penalizedDays = max(0, $lateDays - $graceDays);
        if ($penalizedDays <= 0) {
            return 0;
        }

        return match ($mode) {
            'fixed_per_late' => round($penalizedDays * $fixedAmount, 2),
            'fixed_per_minute' => round($lateMinutes * $fixedAmount, 2),
            default => round($penalizedDays * ($basicSalary / max(1, Carbon::parse($startDate)->daysInMonth)), 2),
        };
    }

    /**
     * Overtime pay from the attendance log per the HR overtime policy.
     *
     *   - salary_based : hourly rate = (basic_salary / 208) x multiplier
     *   - fixed_rate   : hourly rate = the configured fixed amount
     */
    private function overtimeFromAttendance(int $employeeId, string $startDate, string $endDate, float $basicSalary, array $settings): float
    {
        $mode = $settings['overtime_mode'] ?? 'salary_based';
        $totalOtHours = (float) Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('overtime_hours');

        if ($totalOtHours <= 0) {
            return 0;
        }

        if ($mode === 'fixed_rate') {
            $hourlyRate = (float) ($settings['overtime_fixed_rate'] ?? 0);
        } else {
            $multiplier = (float) ($settings['overtime_multiplier'] ?? 1.5);
            $hourlyRate = ($basicSalary / 208) * $multiplier;
        }

        return round($totalOtHours * $hourlyRate, 2);
    }

    /**
     * Read the overtime / late-fee rule settings from the hr_settings store.
     */
    private function hrSettings(): array
    {
        return [
            'overtime_mode' => HrSetting::getValue('overtime_mode', 'salary_based'),
            'overtime_fixed_rate' => HrSetting::getValue('overtime_fixed_rate', '100'),
            'overtime_multiplier' => HrSetting::getValue('overtime_multiplier', '1.5'),
            'late_fee_mode' => HrSetting::getValue('late_fee_mode', 'salary_based'),
            'late_fee_fixed_amount' => HrSetting::getValue('late_fee_fixed_amount', '50'),
            'late_grace_days' => HrSetting::getValue('late_grace_days', '3'),
        ];
    }
}
