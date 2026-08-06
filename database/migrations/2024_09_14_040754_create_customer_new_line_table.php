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
        Schema::create('customer_new_line', function (Blueprint $table) {
            $table->id();
            $table->string('name');  // First name of the contact
            // Last name of the contact
            $table->string('email')->nullable();  // Email, nullable if optional
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->string('package')->nullable();
            $table->string('otc')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_new_line');
    }
};
