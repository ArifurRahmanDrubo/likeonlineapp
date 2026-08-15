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
        Schema::create('generatedsallary', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 10, 2); // Base salary
            $table->decimal('allowances', 10, 2)->default(0); // Total allowances
            $table->decimal('late_fees', 10, 2)->default(0); // Total late fees
            $table->decimal('overtime', 10, 2)->default(0); // Total overtimessss
            $table->decimal('advances', 10, 2)->default(0); // Total advances
            $table->decimal('absent_deduction', 10, 2)->default(0); // Total advances
            $table->decimal('total_salary', 10, 2); // Total salary after deductions
            $table->string('status'); // Date of the payroll
            $table->string('approval_status')->default('pending_approval');
            $table->string('payment_status')->nullable();
    
            $table->decimal('paid_amount',10,2 )->nullable(); // Date of the payroll
            $table->decimal('due_amount', 10,2)->nullable(); // Date of the payroll
            $table->string('payroll_date')->nullable(); // Date of the payroll
            $table->string('sallary_month')->nullable(); // Date of the payroll
            $table->timestamps();

            // Foreign key constraint (assuming you have an 'employees' table)
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generatedsallary');
    }
};
