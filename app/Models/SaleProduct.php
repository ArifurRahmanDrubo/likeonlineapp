<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleProduct extends Model
{
    use HasFactory;

    protected $table = 'sales_product';
    protected $fillable = ['sale_id', 'product_id',  'qty', 'price'];

    function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
