<?php

namespace App\Models;

use App\Models\ResellerZone;
use App\Models\ResellerSubzone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ResellerBox extends Model
{

    use HasFactory;
    protected $fillable = [
        'box_name',
        'details',
        'reseller_subzone_id',
        'reseller_zone_id',
        'mac_reseller_id'
    ];

    // Relationships
    public function resellersubzone()
    {
        return $this->belongsTo(ResellerSubzone::class);
    }

    public function resellerzone()
    {
        return $this->belongsTo(ResellerZone::class);
    }
    public function macreseller()
    {
        return $this->belongsTo(MacReseller::class);
    }
}
