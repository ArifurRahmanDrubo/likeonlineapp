<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $table = 'shifts';

    protected $guarded = [];

    /**
     * Employees assigned to this shift.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
