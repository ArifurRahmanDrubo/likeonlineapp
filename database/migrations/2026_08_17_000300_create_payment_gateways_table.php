<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `payment_gateways` stores the dynamic, DB-driven gateway configuration
     * (bKash / Nagad / SSLCommerz). Credentials are kept in the JSON column
     * so each gateway can carry its own set of keys without schema churn.
     */
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->enum('name', ['bkash', 'nagad', 'sslcommerz'])->unique();
            $table->string('title');
            $table->boolean('is_active')->default(false);
            $table->enum('mode', ['sandbox', 'live'])->default('sandbox');
            $table->json('credentials')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
