<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MikrotikServer extends Model
{
    use HasFactory;
    protected $table = 'mikrotikservers';
    protected $fillable = [
        'serverName',
        'serverip',
        'Username',
        'password',
        'port',
        'version',
        'timeout',
        'status',
    ];
    // protected $hidden = [
    //     'password',
    // ];

    public function customer()
    {
        return $this->hasMany(Customer::class);
    }
}
