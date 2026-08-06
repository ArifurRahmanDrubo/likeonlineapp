<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResignRule extends Model
{
    use HasFactory;
    protected $fillable = [
        'resignrule',
        'details',
    ];
}
