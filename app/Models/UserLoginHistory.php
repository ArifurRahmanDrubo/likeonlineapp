<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLoginHistory extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'country',
        'region',
        'city',
        'zip',
        'organization',
        'status',
        'logged_in_at',
        'logged_out_at',
        'duration'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
