<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MacReseller extends Model
{

    use HasFactory;

    protected $fillable = [
        'name',
        'nid',
        'phoneno',
        'mobile',
        'email',
        'reseller_prefix',
        'reseller_code',
        'district',
        'upzila',
        'setprefix',
        'zone',
        'reseller_type',
        'rechargableamount',
        'address',
        'bussinessname',
        'tariff',
        'disabled_client',
        'minimumbalance',
        'username',
        'password',
        'confirm_password',
        'macresellerlogo',
        'tariff_id',
    ];

    public function menus()
    {
        return $this->hasMany(Menu::class);
    }
    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }
}
