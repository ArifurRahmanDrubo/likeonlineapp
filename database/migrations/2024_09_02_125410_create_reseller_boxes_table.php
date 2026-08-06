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
        Schema::create('reseller_boxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mac_reseller_id')->nullable();
            $table->string('box_name');
            $table->text('details')->nullable();
            $table->unsignedBigInteger('reseller_subzone_id')->nullable();
            $table->unsignedBigInteger('reseller_zone_id')->nullable();
            // Foreign key constraints
            $table->foreign('reseller_subzone_id')->references('id')->on('reseller_subzones')->onDelete('cascade');
            $table->foreign('reseller_zone_id')->references('id')->on('reseller_zones')->onDelete('cascade');
            $table->foreign('mac_reseller_id')->references('id')->on('mac_resellers')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_boxes');
    }
};
