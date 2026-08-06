<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TariffPackage extends Model
{
    protected $fillable = [
        'tariff_id',
        'package_name',
        'server',
        'server_id',
        'protocol',
        'profile',
        'package_rate',
        'selling_rate',
        'validity_days',
        'minimum_activation_days'
    ];

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }
}
