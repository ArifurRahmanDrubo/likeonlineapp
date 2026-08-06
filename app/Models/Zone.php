<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;
    protected $fillable = ['zone_name', 'details'];
    public function subzones()
    {
        return $this->hasMany(Subzone::class);
    }
    public function boxes()
    {
        return $this->hasMany(Box::class);
    }
}
