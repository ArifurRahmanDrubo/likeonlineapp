<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subzone extends Model
{
    protected $fillable = ['subzone_name', 'details', 'zone_id'];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
}
