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
        Schema::create('tariff_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tariff_id');
            $table->string('package_name');
            $table->string('server');
            $table->integer('server_id');
            $table->string('protocol');
            $table->string('profile');
            $table->decimal('package_rate', 10, 2);
            $table->decimal('selling_rate', 10, 2)->nullable();
            $table->integer('validity_days');
            $table->integer('minimum_activation_days');
            $table->foreign('tariff_id')->references('id')->on('tariffs')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_packages');
    }
};
