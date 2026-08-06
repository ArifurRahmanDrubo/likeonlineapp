<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Generatedsallary extends Model
{
    use HasFactory;
  protected $guarded = []; 


    // Accessor to calculate total salary
    // Optionally, you can define any relationships or custom methods here
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
