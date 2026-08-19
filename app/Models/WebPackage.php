<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'price',
        'package_type',
        'button_label',
        'features',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'status' => 'boolean',
    ];
}
