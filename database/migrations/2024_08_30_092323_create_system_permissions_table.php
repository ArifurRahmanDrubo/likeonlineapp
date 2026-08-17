<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_permissions', function (Blueprint $table) {
            $table->id();
            $table->boolean('payroll_with_late_fees')->default(false);
            $table->boolean('payroll_with_overtime')->default(false);
            $table->boolean('payroll_with_absence')->default(false);
            $table->boolean('payment_status_wise_client_disabled')->default(false);
            $table->boolean('company_name_invoice')->default(false);
            $table->string('block_mikrotik_profile')->nullable(); // Stores custom blocked profile name or string
            $table->boolean('save_comment_in_mikrotik')->default(false);
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
