<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageChanged extends Model
{
    use HasFactory;
    protected $table = 'package_changed';

    // Optionally, define the fillable properties
    protected $fillable = [
        'customer_id',
        'server',
        'protocoltype',
        'profile',
        'package',
        'monthlybill',
        'notes',
        'executiondate'
    ];
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
