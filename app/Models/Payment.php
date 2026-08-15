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

    /**
     * The user who created/submitted this payment (FK: created_by).
     * Legacy rows may store the user's name string instead of an ID,
     * so the relation can be null for those rows.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who approved this payment (FK: approved_by).
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
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
