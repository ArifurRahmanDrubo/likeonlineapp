<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    // Specify the attributes that are mass assignable
  protected $guarded = []; 

    // Define any relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
