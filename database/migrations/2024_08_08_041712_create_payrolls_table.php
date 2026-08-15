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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->unique()
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->decimal('basic_salary', 10, 2);
            $table->decimal('advance_balance', 10, 2)->default(0);
            $table->decimal('due_balance', 10, 2)->default(0);

            $table->enum('status', ['active', 'inactive'])
                ->default('active');

            $table->timestamps();
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
