<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Generatedsallary — Monthly Payslip / Transaction History (1-to-many).
 *
 * One row per employee + sallary_month with the full salary breakdown,
 * the approval workflow (pending_approval -> approved / rejected) and the
 * payment status (unpaid / partial / paid).
 */
class Generatedsallary extends Model
{
    use HasFactory;

    protected $table = 'generatedsallary';

    protected $fillable = [
        'employee_id',
        'payroll_id',
        'sallary_month',
        'payroll_date',
        'basic_salary',
        'allowances',
        'overtime',
        'late_fees',
        'absent_deduction',
        'advances',
        'total_salary',
        'paid_amount',
        'due_amount',
        'approval_status',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'sallary_month' => 'date',
        'payroll_date' => 'date',
        'basic_salary' => 'decimal:2',
        'allowances' => 'decimal:2',
        'overtime' => 'decimal:2',
        'late_fees' => 'decimal:2',
        'absent_deduction' => 'decimal:2',
        'advances' => 'decimal:2',
        'total_salary' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The master ledger this payslip was generated against.
     */
    public function payroll()
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }
}
