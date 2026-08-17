<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'title', 'template', 'placeholders', 'is_enabled'];

    protected $casts = [
        'placeholders' => 'array',
        'is_enabled' => 'boolean',
    ];
}
