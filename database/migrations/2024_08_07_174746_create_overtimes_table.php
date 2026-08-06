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
        Schema::create('overtimes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('rate_per_hour', 8, 2); // Overtime rate per hour
            $table->integer('hours_overtime'); // Number of overtime hours
            $table->decimal('total_amount', 10, 2)->nullable(); // Total amount calculated
            $table->string('date'); // Date of the overtime
            $table->text('notes')->nullable(); // Optional description
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
        Schema::dropIfExists('overtimes');
    }
};
