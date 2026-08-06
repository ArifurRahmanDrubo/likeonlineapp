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
        Schema::create('reseller_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mac_reseller_id')->nullable();
            $table->string('name'); // Name field
            $table->string('status')->default('active');
            $table->foreign('mac_reseller_id')->references('id')->on('mac_resellers')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_positions');
    }
};
