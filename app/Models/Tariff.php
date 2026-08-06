<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    protected $fillable = [
        'tariff_name',
        'assigned_mac_resellers',
        'created_by',
        'created_on',

    ];

    public function packages()
    {
        return $this->hasMany(TariffPackage::class);
    }
    public function macreseller()
    {
        return $this->hasOne(MacReseller::class);
    }
    public function getTariffPackagesAttribute()
    {
        return $this->packages->pluck('package_name')->implode(', ');
    }

    public function getTariffProfilesAttribute()
    {
        return $this->packages->pluck('profile')->implode(', ');
    }

    public function getTariffServersAttribute()
    {
        return $this->packages->pluck('server')->unique()->implode(', ');
    }
}
