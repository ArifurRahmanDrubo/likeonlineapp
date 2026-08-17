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
        Schema::create('invoice_setup', function (Blueprint $table) {
            $table->id(); // Creates an auto-incrementing primary key column 'id'
            $table->string('company_name');
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->string('image')->nullable();
            $table->string('image_public_id')->nullable();
            $table->text('invoice_note')->nullable();
            $table->timestamps(); // Adds 'created_at' and 'updated_at' columns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_setup');
    }
};
