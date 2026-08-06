<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = []; 


    // Define any relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            $payment->payment_id = self::generateUniqueId();
        });
    }

    // Function to generate unique ID
    private static function generateUniqueId()
    {
        do {
            $payment_id = 'PAY-' . strtoupper(bin2hex(random_bytes(3))); // e.g., PAY-1A2B3C
        } while (self::where('payment_id', $payment_id)->exists());

        return $payment_id;
    }
}
