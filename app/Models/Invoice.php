<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    // Specify the attributes that are mass assignable
  protected $guarded = []; 

    // Define any relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Payments received for this invoice's customer.
     * Both tables carry customer_id, so payments can be reached directly
     * from the invoice without an extra belongsTo hop.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'customer_id', 'customer_id');
    }
}
