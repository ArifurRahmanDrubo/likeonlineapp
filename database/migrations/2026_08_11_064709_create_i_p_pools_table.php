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
        Schema::create('i_p_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('ranges')->nullable();
            $table->string('mikrotik_id')->nullable();
            $table->integer('server_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('i_p_pools');
    }
};
