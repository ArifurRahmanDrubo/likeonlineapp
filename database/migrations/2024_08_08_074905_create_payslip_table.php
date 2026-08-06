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
        Schema::create('payslip', function (Blueprint $table) {
            $table->id();
            $table->string('payslip_id')->unique();
            $table->unsignedBigInteger('employee_id');
            $table->string('payment_date');
            $table->string('employee_code');
            $table->string('payment_by');
            $table->string('payment_info');
            $table->decimal('payment_amount', 8, 2)->default(0);
            $table->string('transaction_no')->nullable();
            $table->text('notes')->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslip');
    }
};
