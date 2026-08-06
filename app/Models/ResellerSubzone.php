<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerSubzone extends Model
{
    use HasFactory;

    protected $fillable = ['subzone_name', 'details', 'reseller_zone_id', 'mac_reseller_id'];

    public function resellerzone()
    {
        return $this->belongsTo(ResellerZone::class);
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
