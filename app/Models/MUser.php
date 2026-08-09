<?php

namespace App\Models;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MUser extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function customer()
{
    // Customer টেবিলের 'username' এর সাথে MUser টেবিলের 'name' ম্যাচ করবে
    return $this->hasOne(Customer::class, 'username', 'name');
}
    public function server()
{
    // MikrotikServer টেবিলের 'id' এর সাথে MUser টেবিলের 'server_id' ম্যাচ করবে
    return $this->hasOne(MikrotikServer::class, 'id', 'server_id');
}
 
}
