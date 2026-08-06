<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Upzila extends Model
{
    use HasFactory;
    protected $fillable = [
        'upzilaname',
        'details',
    ];
}
