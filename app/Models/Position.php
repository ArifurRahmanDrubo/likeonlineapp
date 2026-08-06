<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    protected $table = 'positions';

    // The attributes that are mass assignable.
    protected $fillable = [
        'name',
        'status',
    ];
}
