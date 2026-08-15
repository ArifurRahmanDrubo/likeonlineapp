<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    // The table associated with the model.
  protected $guarded = []; 

    // The attributes that should be cast to native types.
    protected $appends = ['formatted_id', 'department_name', 'position_name'];
    public function getFormattedIdAttribute()
    {
        return 'EMP-' . str_pad($this->attributes['id'], 4, '0', STR_PAD_LEFT);
    }
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
    public function position()
    {
        return $this->belongsTo(Position::class);
    }
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
    public function getDepartmentNameAttribute()
    {
        return $this->department ? $this->department->departmenttype : null;
    }
    public function getPositionNameAttribute()
    {
        return $this->position ? $this->position->name : null;
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
    public function allowance()
    {
        return $this->hasMany(Allowance::class);
    }
    public function advance()
    {
        return $this->hasMany(Advance::class);
    }
    public function latefee()
    {
        return $this->hasMany(LateFee::class);
    }
    public function overtime()
    {
        return $this->hasMany(Overtime::class);
    }
    public function payslip()
    {
        return $this->hasMany(Payslip::class);
    }
    public function payroll()
    {
        return $this->hasOne(Payroll::class);
    }
    public function generatedsalary()
    {
        return $this->hasMany(Generatedsallary::class);
    }
    /**
     * The most recent monthly payslip snapshot (used by the payroll UI).
     */
    public function latestPayslip()
    {
        return $this->hasOne(Generatedsallary::class)->latestOfMany('sallary_month');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($employee) {
            // Delete related records
            if ($employee->attendances) {
                $employee->attendances()->delete();
            }
            if ($employee->allowance) {
                $employee->allowance()->delete();
            }
            if ($employee->advance) {
                $employee->advance()->delete();
            }
            if ($employee->latefee) {
                $employee->latefee()->delete();
            }
            if ($employee->overtime) {
                $employee->overtime()->delete();
            }
            if ($employee->generatedsalary) {
                $employee->generatedsalary()->delete();
            }
            if ($employee->payroll) {
                $employee->payroll()->delete();
            }
            if ($employee->payslip) {
                $employee->payslip()->delete();
            }

            // Delete related payroll record

            // Optionally, delete the related user
            if ($employee->user) {
                $employee->user->delete();
            }
        });
    }
}
