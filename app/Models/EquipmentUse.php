<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EquipmentUse extends Model
{
    use HasFactory;
  protected $guarded = []; 

    function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
