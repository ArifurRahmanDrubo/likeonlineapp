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
        Schema::create('mac_resellers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('tariff_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nid')->nullable();
            $table->string('phoneno')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('reseller_prefix')->nullable();
            $table->string('reseller_code')->nullable();
            $table->string('district')->nullable();
            $table->string('upzila')->nullable();
            $table->string('setprefix')->nullable();
            $table->string('zone')->nullable();
            $table->string('reseller_type')->nullable();
            $table->decimal('rechargableamount', 10, 2)->nullable();
            $table->text('address')->nullable();
            $table->string('bussinessname')->nullable();
            $table->string('tariff')->nullable();
            $table->string('disabled_client')->nullable();
            $table->decimal('minimumbalance', 10, 2)->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('confirm_password')->nullable();
            $table->string('macresellerlogo')->nullable();
            $table->foreign('tariff_id')->references('id')->on('tariffs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mac_resellers');
    }
};
