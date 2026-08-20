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
     * @return array{generated:int, skipped:int, details:array<int,array<string,mixed>>}
     */
    public function generate(int $year, int $month): array
    {
        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $startDate  = $monthStart->format('Y-m-d');
        $endDate    = $monthStart->copy()->endOfMonth()->format('Y-m-d');
        $salaryMonth = $monthStart->format('Y-m-d');

        $settings = $this->hrSettings();

        $system = SystemPermission::first();
        $withLateFees = $system ? $system->isEnabled('payroll_with_late_fees', true) : true;
        $withOvertime = $system ? $system->isEnabled('payroll_with_overtime', true) : true;
        $withAbsence  = $system ? $system->isEnabled('payroll_with_absence', true) : true;

        $results = ['generated' => 0, 'skipped' => 0, 'details' => []];

        // Process in chunks to optimize memory
        Employee::query()->chunk(100, function ($employees) use (
            $startDate, $endDate, $salaryMonth, $settings, 
            $withLateFees, $withOvertime, $withAbsence, &$results
        ) {
            foreach ($employees as $employee) {
                DB::transaction(function () use (
                    $employee, $startDate, $endDate, $salaryMonth, $settings, 
                    $withLateFees, $withOvertime, $withAbsence, &$results
                ) {
                    // Check duplicate generation
                    $alreadyGenerated = Generatedsallary::where('employee_id', $employee->id)
                        ->where('sallary_month', $salaryMonth)
                        ->exists();

                    if ($alreadyGenerated) {
                        $results['skipped']++;
                        $results['details'][] = [
                            'employee_id' => $employee->id,
                            'name'        => $employee->name,
                            'status'      => 'skipped'
                        ];
                        return;
                    }

                    // Find or create Master Ledger & keep basic salary updated
                    $ledger = Payroll::firstOrCreate(
                        ['employee_id' => $employee->id],
                        [
                            'basic_salary'    => round($employee->basic_salary ?? 0),
                            'advance_balance' => 0,
                            'due_balance'     => 0,
                            'status'          => 'unpaid',
                        ]
                    );

                    $basicSalary = round((float) ($employee->basic_salary ?? 0));

                    if ((float)$ledger->basic_salary !== $basicSalary) {
                        $ledger->update(['basic_salary' => $basicSalary]);
                    }

                    $allowances  = round($this->totalAllowances($employee->id, $startDate, $endDate));
                    $attendance  = $this->attendanceSummary($employee->id, $startDate, $endDate);

                    $absentDeduction = $withAbsence
                        ? round($this->absentDeduction($employee, $startDate, $endDate))
                        : 0;

                    $lateFees = $withLateFees
                        ? round($this->lateFeesFromAttendance($employee->id, $startDate, $endDate, $basicSalary, $settings)
                            + $this->totalLateFees($employee->id, $startDate, $endDate))
                        : 0;

                    $overtime = $withOvertime
                        ? round($this->overtimeFromAttendance($employee->id, $startDate, $endDate, $basicSalary, $settings)
                            + $this->totalOvertime($employee->id, $startDate, $endDate))
                        : 0;

                    // Deduct advance installments
                    $advanceInstallment = round($this->applyAdvanceInstallments($employee->id));

                    $totalSalary = ($basicSalary + $allowances + $overtime) - ($lateFees + $advanceInstallment + $absentDeduction);
                    $totalSalary = max(0, round($totalSalary));

                    // Create Payslip History
                    Generatedsallary::create([
                        'employee_id'      => $employee->id,
                        'payroll_id'        => $ledger->id,
                        'approval_status'  => 'pending_approval',
                        'payment_status'   => 'unpaid',
                        'paid_amount'      => 0,
                        'due_amount'       => $totalSalary,
                        'sallary_month'    => $salaryMonth,
                        'payroll_date'     => $endDate,
                        'basic_salary'     => $basicSalary,
                        'allowances'       => $allowances,
                        'late_fees'        => $lateFees,
                        'overtime'         => $overtime,
                        'advances'         => $advanceInstallment,
                        'absent_deduction' => $absentDeduction,
                        'total_salary'     => $totalSalary,
                    ]);

                    // Adjust master ledger advance balance safely
                    if ($advanceInstallment > 0) {
                        $newBalance = max(0, round((float)$ledger->advance_balance - $advanceInstallment));
                        $ledger->update(['advance_balance' => $newBalance]);
                    }

                    $results['generated']++;
                    $results['details'][] = [
                        'employee_id'         => $employee->id,
                        'name'                => $employee->name,
                        'status'              => 'generated',
                        'total_salary'        => $totalSalary,
                        'basic_salary'        => $basicSalary,
                        'allowances'          => $allowances,
                        'absent_deduction'    => $absentDeduction,
                        'late_fees'           => $lateFees,
                        'overtime_amount'     => $overtime,
                        'advance_installment' => $advanceInstallment,
                        'attendance'          => $attendance,
                    ];
                });
            }
        });

        return $results;
    }

    private function attendanceSummary(int $employeeId, string $startDate, string $endDate): array
    {
        $baseQuery = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate]);

        return [
            'total_present'      => (int) (clone $baseQuery)->whereIn('status', ['present', 'late'])->count(),
            'total_absent'       => (int) (clone $baseQuery)->where('status', 'absent')->count(),
            'total_paid_leaves'  => (int) (clone $baseQuery)->where('status', 'paid_leave')->count(),
            'total_unpaid_leaves'=> (int) (clone $baseQuery)->where('status', 'unpaid_leave')->count(),
            'total_late_minutes' => round((float) (clone $baseQuery)->where('status', 'late')->sum('late_minutes')),
            'total_ot_hours'     => round((float) (clone $baseQuery)->sum('overtime_hours')),
        ];
    }

    private function applyAdvanceInstallments(int $employeeId): float
    {
        $totalInstallment = 0;

        $advances = Advance::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->lockForUpdate()
            ->get();

        foreach ($advances as $advance) {
            $remaining   = round((float) $advance->remaining_amount);
            $installment = round((float) ($advance->monthly_installment ?? $remaining));
            $apply       = min($installment, $remaining);

            $totalInstallment += $apply;

            $newRemaining = round($remaining - $apply);
            $advance->paid_amount      = round((float) $advance->paid_amount + $apply);
            $advance->remaining_amount = max(0, $newRemaining);
            
            if ($advance->remaining_amount <= 0) {
                $advance->status = 'paid';
            }
            $advance->save();
        }

        return round($totalInstallment);
    }

    private function totalAllowances(int $employeeId, string $startDate, string $endDate): float
    {
        return round((float) Allowance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount'));
    }

    private function totalLateFees(int $employeeId, string $startDate, string $endDate): float
    {
        return round((float) LateFee::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('total_amount'));
    }

    private function totalOvertime(int $employeeId, string $startDate, string $endDate): float
    {
        return round((float) Overtime::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('total_amount'));
    }

    private function absentDeduction(Employee $employee, string $startDate, string $endDate): float
    {
        $basicSalary = (float) ($employee->basic_salary ?? 0);
        $daysInMonth = Carbon::parse($startDate)->daysInMonth;

        if ($daysInMonth <= 0) {
            return 0;
        }

        $dailySalary = $basicSalary / $daysInMonth;
        $unpaidDays  = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['absent', 'unpaid_leave'])
            ->count();

        return round($dailySalary * $unpaidDays);
    }

    private function lateFeesFromAttendance(int $employeeId, string $startDate, string $endDate, float $basicSalary, array $settings): float
    {
        $mode        = $settings['late_fee_mode'] ?? 'salary_based';
        $fixedAmount = (float) ($settings['late_fee_fixed_amount'] ?? 0);

        if ($mode === 'fixed_per_minute') {
            $totalLateMinutes = (float) Attendance::where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate, $endDate])
                ->where('status', 'late')
                ->sum('late_minutes');

            return round($totalLateMinutes * $fixedAmount);
        }

        $lateDays = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'late')
            ->count();

        if ($lateDays <= 0) {
            return 0;
        }

        return match ($mode) {
            'fixed_per_late' => round($lateDays * $fixedAmount),
            default          => round($lateDays * ($basicSalary / max(1, Carbon::parse($startDate)->daysInMonth))),
        };
    }

    private function overtimeFromAttendance(int $employeeId, string $startDate, string $endDate, float $basicSalary, array $settings): float
    {
        $mode         = $settings['overtime_mode'] ?? 'salary_based';
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

        return round($totalOtHours * $hourlyRate);
    }

    private function hrSettings(): array
    {
        return [
            'overtime_mode'         => HrSetting::getValue('overtime_mode', 'salary_based'),
            'overtime_fixed_rate'   => HrSetting::getValue('overtime_fixed_rate', '100'),
            'overtime_multiplier'   => HrSetting::getValue('overtime_multiplier', '1.5'),
            'late_fee_mode'         => HrSetting::getValue('late_fee_mode', 'salary_based'),
            'late_fee_fixed_amount' => HrSetting::getValue('late_fee_fixed_amount', '50'),
        ];
    }
}