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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 10, 2); // Base salary
            $table->decimal('allowances', 10, 2)->default(0); // Total allowances
            $table->decimal('late_fees', 10, 2)->default(0); // Total late fees
            $table->decimal('overtime', 10, 2)->default(0); // Total overtimessss
            $table->decimal('advances', 10, 2)->default(0); // Total advances
            $table->decimal('absent_deduction', 10, 2)->default(0); // Total advances
            $table->decimal('total_salary', 10, 2); // Total salary after deductions
            $table->string('payroll_date'); // Date of the payroll
            $table->string('sallary_month'); // Date of the payroll
            $table->string('notes')->nullable(); // Date of the payroll
            $table->enum('status', ['paid', 'unpaid'])->default('unpaid'); // Date of the payroll
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
        Schema::dropIfExists('payrolls');
    }
};
