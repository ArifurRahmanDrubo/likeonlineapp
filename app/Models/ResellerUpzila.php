<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerUpzila extends Model
{
    use HasFactory;
    protected $fillable = [
        'upzilaname',
        'details',
        'mac_reseller_id'
    ];

    public function macreseller()
    {
        return $this->belongsTo(MacReseller::class);
    }
}
