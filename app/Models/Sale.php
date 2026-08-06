<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sale extends Model
{
    use HasFactory;
    protected $fillable = ['total',  'customer_id'];

    function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
