<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusChanged extends Model
{
    use HasFactory;

    protected $table = 'status_changed';

    protected $fillable = [
        'customer_id',
        'old_billingstatus',
        'billingstatus',
        'notes',
        'requested_by',
        'executiondate',
        'status',
        'error_log',
        'rejection_reason',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
