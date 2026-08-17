<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceSetup extends Model
{
    use HasFactory;

    // The migration creates the singular table name `invoice_setup` — Eloquent
    // would otherwise pluralize it to `invoice_setups` and every query fails.
    protected $table = 'invoice_setup';

    protected $guarded = []; 
}
