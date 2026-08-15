<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Payroll — Master Ledger (1-to-1 with Employee).
 *
 * Holds the running salary account of an employee:
 *   basic_salary    : the employee's current base salary
 *   advance_balance : outstanding advance amount given to the employee
 *   due_balance     : total unpaid salary owed to the employee
 *   status          : active / inactive
 *
 * The monthly salary snapshots live in Generatedsallary (1-to-many).
 */
class Payroll extends Model
{
    use HasFactory;

    protected $table = 'payrolls';

    protected $fillable = [
        'employee_id',
        'basic_salary',
        'advance_balance',
        'due_balance',
        'status',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'advance_balance' => 'decimal:2',
        'due_balance' => 'decimal:2',
    ];

    /**
     * The employee this ledger belongs to.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Monthly payslip snapshots generated against this ledger.
     */
    public function generatedsalaries()
    {
        return $this->hasMany(Generatedsallary::class, 'payroll_id');
    }
}
