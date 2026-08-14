<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the 'partial' status to invoices so a payment that only covers part
     * of an invoice's due balance can be recorded without marking it Paid or
     * leaving it Unpaid. (invoices.amount is the running due balance — there
     * is no separate due_amount column.)
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', ['paid', 'unpaid', 'partial'])->default('unpaid')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', ['paid', 'unpaid'])->default('unpaid')->change();
        });
    }
};
