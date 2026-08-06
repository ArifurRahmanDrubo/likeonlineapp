<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    use HasFactory;
    protected $table = 'overtimes';

    protected $fillable = [
        'employee_id',
        'rate_per_hour',
        'hours_overtime',
        'total_amount',
        'date',
        'notes',
    ];

    // Accessor to calculate total amount
    public function getTotalAmountAttribute()
    {
        return $this->rate_per_hour * $this->hours_overtime;
    }

    // Optionally, you can define any relationships or custom methods here
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Method to save total amount
    public function save(array $options = [])
    {
        $this->total_amount = $this->getTotalAmountAttribute();
        parent::save($options);
    }
}
