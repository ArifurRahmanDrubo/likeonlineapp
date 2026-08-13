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
        Schema::create('status_changed', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('billingstatus'); // Add your desired field type and constraints
            $table->text('notes')->nullable(); // Allow null values
            $table->string('executiondate');
            $table->string('old_billingstatus')->nullable()->after('customer_id');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending')->after('executiondate');
            $table->text('error_log')->nullable()->after('status');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade'); // Date type for execution date
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_changed');
    }
};
