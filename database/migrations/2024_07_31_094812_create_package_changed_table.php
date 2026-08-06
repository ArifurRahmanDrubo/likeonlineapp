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
        Schema::create('package_changed', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('server'); // Assuming server is a string; adjust if necessary
            $table->string('protocoltype')->nullable(); // Protocol type as a string
            $table->string('profile'); // Profile as a string
            $table->string('package')->nullable(); // Package name or type
            $table->decimal('monthlybill', 10, 2); // Monthly bill with precision for currency
            $table->text('notes')->nullable(); // Notes can be nullable
            $table->string('executiondate');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->timestamps(); // Created_at and updated_at timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_changed');
    }
};
