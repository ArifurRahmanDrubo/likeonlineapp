<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;
  protected $guarded = []; 

    // Define a relationship to the Employee model
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
