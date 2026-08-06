<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    use HasFactory;
    protected $table = 'payslip';
    protected $fillable = [
        'employee_id',
        'payment_date',
        'employee_code',
        'payment_by',
        'payment_info',
        'payment_amount',
        'transaction_no',
        'notes',
        'payslip_id',

    ];

    // Define any relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payslip) {
            $payslip->payslip_id = self::generateUniqueId();
        });
    }

    // Function to generate unique ID
    private static function generateUniqueId()
    {
        do {
            $payslip_id = 'PAY-' . strtoupper(bin2hex(random_bytes(5))); // e.g., PAY-1A2B3C
        } while (self::where('payslip_id', $payslip_id)->exists());

        return $payslip_id;
    }
}
