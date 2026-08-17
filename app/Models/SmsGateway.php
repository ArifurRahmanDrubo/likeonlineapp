<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsGateway extends Model
{
    use HasFactory;

    protected $fillable = ['provider', 'name', 'api_key', 'sender_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
