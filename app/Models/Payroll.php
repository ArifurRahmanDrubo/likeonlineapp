<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $table = 'payrolls';

    protected $fillable = [
        'employee_id',
        'basic_salary',
        'allowances',
        'late_fees',
        'overtime',
        'advances',
        'absent_deduction',
        'total_salary',
        'payroll_date',
        'notes',
        'status',
        'sallary_month'
    ];


    // Accessor to calculate total salary
    // Optionally, you can define any relationships or custom methods here
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
