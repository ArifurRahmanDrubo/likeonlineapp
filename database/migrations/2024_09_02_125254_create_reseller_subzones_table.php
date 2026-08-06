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
        Schema::create('reseller_subzones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mac_reseller_id')->nullable();
            $table->unsignedBigInteger('reseller_zone_id')->nullable();
            $table->string('subzone_name'); // Field for zone name
            $table->text('details')->nullable();
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
        Schema::dropIfExists('reseller_subzones');
    }
};
