<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    use HasFactory;
    protected $guarded = []; 

    // Relationships
    public function subzone()
    {
        return $this->belongsTo(Subzone::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
}
