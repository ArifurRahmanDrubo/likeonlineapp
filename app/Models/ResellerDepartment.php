<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerDepartment extends Model
{
    use HasFactory;
    protected $fillable = [
        'departmenttype',
        'details',
        'mac_reseller_id'
    ];

    public function macreseller()
    {
        return $this->belongsTo(MacReseller::class);
    }
}
