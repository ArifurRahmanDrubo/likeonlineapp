<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerZone extends Model
{
    use HasFactory;

    protected $fillable = ['zone_name', 'details', 'mac_reseller_id'];
    public function resellersubzones()
    {
        return $this->hasMany(ResellerSubzone::class);
    }
    public function resellerboxes()
    {
        return $this->hasMany(ResellerBox::class);
    }


    public function macreseller()
    {
        return $this->belongsTo(MacReseller::class);
    }
}
