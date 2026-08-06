<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerDistrict extends Model
{
    use HasFactory;

    protected $fillable = [
        'districtname',
        'details',
        'mac_reseller_id'
    ];

    public function macreseller()
    {
        return $this->belongsTo(MacReseller::class);
    }
}
