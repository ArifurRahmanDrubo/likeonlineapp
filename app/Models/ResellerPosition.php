<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerPosition extends Model
{
    use HasFactory;



    protected $table = 'reseller_positions';

    // The attributes that are mass assignable.
    protected $fillable = [
        'name',
        'status',
        'mac_reseller_id'
    ];
    public function macreseller()
    {
        return $this->belongsTo(MacReseller::class);
    }
}
