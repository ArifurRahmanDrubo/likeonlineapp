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
        Schema::create('boxes', function (Blueprint $table) {
            $table->id();
            $table->string('box_name');
            $table->text('details')->nullable();
            $table->unsignedBigInteger('subzone_id')->nullable();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('subzone_id')->references('id')->on('subzones')->onDelete('cascade');
            $table->foreign('zone_id')->references('id')->on('zones')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boxes');
    }
};
