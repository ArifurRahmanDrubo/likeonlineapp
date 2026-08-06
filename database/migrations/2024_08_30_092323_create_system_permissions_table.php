<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_with_late_fees')->nullable();
            $table->string('payroll_with_overtime')->nullable();
            $table->string('payroll_with_absence')->nullable();
            $table->string('payment_status_wise_client_disabled')->nullable();
            $table->string('company_name_invoice')->nullable();
            $table->string('block_mikrotik_profile')->nullable();
            $table->string('save_comment_in_mikrotik')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_permissions');
    }
};
