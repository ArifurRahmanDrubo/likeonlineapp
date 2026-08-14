<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a payment status to generated_bills so the current month's bill
     * can be tracked as 'unpaid' and kept in sync when a customer's package
     * or monthly bill is edited.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('generated_bills', 'status')) {
            Schema::table('generated_bills', function (Blueprint $table) {
                $table->string('status')->default('unpaid')->after('generated_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('generated_bills', 'status')) {
            Schema::table('generated_bills', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
