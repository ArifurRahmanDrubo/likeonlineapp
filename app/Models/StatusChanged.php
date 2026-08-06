<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusChanged extends Model
{
    use HasFactory;
    protected $table = 'status_changed';

    // Optionally, define the fillable properties
    protected $fillable = ['billingstatus', 'customer_id', 'notes', 'executiondate'];
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
