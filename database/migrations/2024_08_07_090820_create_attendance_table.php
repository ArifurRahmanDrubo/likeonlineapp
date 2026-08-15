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
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('date');
            $table->enum('status', [
                'present',
                'absent',
                'late',
                'paid_leave',
                'unpaid_leave',
                'holiday',
                'off_day'
            ])->default('present');
            $table->unsignedInteger('late_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();
            $table->decimal('overtime_hours', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']); // Ensure each employee has only one record per day
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
