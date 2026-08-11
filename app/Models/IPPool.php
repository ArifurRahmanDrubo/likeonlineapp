<?php

namespace App\Models;

use App\Models\MikrotikServer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IPPool extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function server()
    {
        return $this->belongsTo(MikrotikServer::class, 'server_id');
    }
}
