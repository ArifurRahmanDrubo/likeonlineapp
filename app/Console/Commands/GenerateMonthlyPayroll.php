<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Allowance;
use App\Models\LateFee;
use App\Models\Overtime;
use App\Models\Advance;
use App\Models\Attendance;
use App\Models\Generatedsallary;
use App\Models\SystemPermission;
use Carbon\Carbon;

class GenerateMonthlyPayroll extends Command
{
    protected $signature = 'payroll:generate';
    protected $description = 'Generate payroll for the previous month';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Get the start and end dates of the previous month
        $currentDate = Carbon::now();
        $startOfPreviousMonth = $currentDate->copy()->subMonth()->startOfMonth()->format('Y-m-d');
        $endOfPreviousMonth = $currentDate->copy()->subMonth()->endOfMonth()->format('Y-m-d');

        // Generate payroll for the previous month

        $settings = SystemPermission::first();
        $applyLateFees = $settings ? $settings->payroll_with_late_fees : 'disable';
        $applyOvertime = $settings ? $settings->payroll_with_overtime : 'enable';
        $applyAbsence = $settings ? $settings->payroll_with_absence : 'disable';

        $employees = Employee::all();

        foreach ($employees as $employee) {
            // Check if a payroll record already exists for this employee and month
            $existingPayroll = Payroll::where('employee_id', $employee->id)->first();
            if ($existingPayroll) {
                $existingPayrollforMonth = Payroll::where('employee_id', $employee->id)
                    ->whereBetween('payroll_date', [$startOfPreviousMonth, $endOfPreviousMonth])
                    ->first();
                if ($existingPayrollforMonth) {
                    $this->info('Payroll for the previous month has already been generated.');
                    return;
                } else {
                    // Otherwise, create a new record
                    $basic_salary = $employee->basic_salary;
                    $allowance = $this->calculateTotalAllowances($employee->id, $startOfPreviousMonth, $endOfPreviousMonth);
                    $late_fees = ($applyLateFees === 'enable') ? $this->calculateTotalLateFees($employee->id, $startOfPreviousMonth, $endOfPreviousMonth) : 0;
                    $overtimes =  ($applyOvertime === 'enable') ? $this->calculateTotalOvertime($employee->id, $startOfPreviousMonth, $endOfPreviousMonth) : 0;
                    $advance =   $this->calculateTotalAdvances($employee->id, $startOfPreviousMonth, $endOfPreviousMonth);
                    $absent_deduction = ($applyAbsence === 'enable') ? $this->calculateAbsentDeduction($employee->id, $startOfPreviousMonth, $endOfPreviousMonth) : 0;
                    $total_salary = $basic_salary + $allowance + $late_fees -  $overtimes - $advance -  $absent_deduction;
                    $updated = Payroll::where('employee_id', $employee->id)
                        ->update([
                            'employee_id' => $employee->id,
                            'status' => 'unpaid',
                            'sallary_month' => $currentDate->copy()->subMonth()->format('Y-m-d'),
                            'payroll_date' => $endOfPreviousMonth,
                            'basic_salary' => $basic_salary,
                            'allowances' => $allowance,
                            'late_fees' => $late_fees,
                            'overtime' => $overtimes,
                            'advances' => $advance,
                            'total_salary' => $total_salary,
                            'absent_deduction' => $absent_deduction,
                        ]);
                    Generatedsallary::create([
                        'employee_id' => $employee->id,
                        'sallary_month' => $currentDate->copy()->subMonth()->format('Y-m-d'),
                        'payroll_date' => $endOfPreviousMonth,
                        'basic_salary' => $basic_salary,
                        'allowances' => $allowance,
                        'late_fees' => $late_fees,
                        'overtime' => $overtimes,
                        'advances' => $advance,
                        'total_salary' => $total_salary,
                        'absent_deduction' => $absent_deduction,
                    ]);
                    // Calculate total salary after deductions


                    // Save payroll record

                }
            } else {
                $basic_salary = $employee->basic_salary;
                $allowance = $this->calculateTotalAllowances($employee->id, $startOfPreviousMonth, $endOfPreviousMonth);
                $late_fees = ($applyLateFees === 'enable') ? $this->calculateTotalLateFees($employee->id, $startOfPreviousMonth, $endOfPreviousMonth) : 0;
                $overtimes = ($applyOvertime === 'enable') ? $this->calculateTotalOvertime($employee->id, $startOfPreviousMonth, $endOfPreviousMonth) : 0;
                $advance =   $this->calculateTotalAdvances($employee->id, $startOfPreviousMonth, $endOfPreviousMonth);
                $absent_deduction = ($applyAbsence === 'enable') ? $this->calculateAbsentDeduction($employee->id, $startOfPreviousMonth, $endOfPreviousMonth) : 0;
                $total_salary = $basic_salary + $allowance + $late_fees -  $overtimes - $advance -  $absent_deduction;
                Payroll::create([
                    'employee_id' => $employee->id,
                    'status' => 'unpaid',
                    'sallary_month' => $currentDate->copy()->subMonth()->format('Y-m-d'),
                    'payroll_date' => $endOfPreviousMonth,
                    'basic_salary' => $basic_salary,
                    'allowances' => $allowance,
                    'late_fees' => $late_fees,
                    'overtime' => $overtimes,
                    'advances' => $advance,
                    'total_salary' => $total_salary,
                    'absent_deduction' => $absent_deduction,
                ]);
                Generatedsallary::create([
                    'employee_id' => $employee->id,
                    'sallary_month' => $currentDate->copy()->subMonth()->format('Y-m-d'),
                    'payroll_date' => $endOfPreviousMonth,
                    'basic_salary' => $basic_salary,
                    'allowances' => $allowance,
                    'late_fees' => $late_fees,
                    'overtime' => $overtimes,
                    'advances' => $advance,
                    'total_salary' => $total_salary,
                    'absent_deduction' => $absent_deduction,
                ]);
            }
        }

        $this->info('Monthly payroll for the previous month has been generated.');
    }

    private function calculateTotalAllowances($employeeId, $startDate, $endDate)
    {
        return Allowance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
    }

    private function calculateTotalLateFees($employeeId, $startDate, $endDate)
    {
        return LateFee::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('total_amount');
    }

    private function calculateTotalOvertime($employeeId, $startDate, $endDate)
    {
        return Overtime::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('total_amount');
    }

    private function calculateTotalAdvances($employeeId, $startDate, $endDate)
    {
        return Advance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
    }

    private function calculateAbsentDeduction($employeeId, $startDate, $endDate)
    {
        // Calculate daily salary rate
        $basicSalary = Employee::find($employeeId)->basic_salary;
        $daysInMonth = Carbon::parse($startDate)->daysInMonth;
        $dailySalary = $basicSalary / $daysInMonth;

        // Count absences
        $absences = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'absent')
            ->count();

        return $dailySalary * $absences;
    }
}
