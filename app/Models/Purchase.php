<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Purchase extends Model
{
    use HasFactory;
    protected $fillable = ['total',   'supplier_id',];
    function supplier(): BelongsTo
    {
        return $this->belongsTo(supplier::class);
    }
    function product(): BelongsTo
    {
        return $this->belongsTo(product::class);
    }
}
