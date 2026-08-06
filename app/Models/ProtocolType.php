<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProtocolType extends Model
{
    use HasFactory;
    protected $fillable = [
        'protocol_type',
        'details',
        'status',
    ];
}
