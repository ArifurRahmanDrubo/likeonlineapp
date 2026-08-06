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
        Schema::create('email_setup', function (Blueprint $table) {
            $table->id(); // Creates an auto-incrementing primary key column 'id'
            $table->string('host');
            $table->integer('port');
            $table->string('username');
            $table->string('password');
            $table->string('mail_from_name')->nullable();
            $table->string('mail_from_email');
            $table->enum('encryption', ['SSL', 'TLS']);
            $table->timestamps(); // Adds 'created_at' and 'updated_at' columns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_setup');
    }
};
