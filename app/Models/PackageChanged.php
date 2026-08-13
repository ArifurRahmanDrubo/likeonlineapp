<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageChanged extends Model
{
    use HasFactory;

    protected $table = 'package_changed';

    protected $fillable = [
        'customer_id',
        'old_profile',
        'old_monthlybill',
        'server',
        'protocoltype',
        'profile',
        'package',
        'monthlybill',
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
