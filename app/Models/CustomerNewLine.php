<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerNewLine extends Model
{
    use HasFactory;
    protected $guarded = [];

    /**
     * The table name is singular (`customer_new_line`), so Eloquent's default
     * pluralisation (`customer_new_lines`) must be overridden.
     */
    protected $table = 'customer_new_line';
}
