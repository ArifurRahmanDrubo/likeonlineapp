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
        Schema::create('mikrotikservers', function (Blueprint $table) {
            $table->id();
            $table->string('serverName');
            $table->ipAddress('serverip');
            $table->string('Username');
            $table->string('password');
            $table->integer('port');
            $table->string('version');
            $table->integer('timeout');
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->timestamps(); // Adds created_at and updated_at columns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mikrotikservers');
    }
};
