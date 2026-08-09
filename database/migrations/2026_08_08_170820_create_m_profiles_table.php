<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('m_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('mikrotik_id')->nullable();
            $table->string('name')->nullable();
            $table->string('address_list')->nullable();
            $table->string('bridge_learning')->nullable();
            $table->string('change_tcp_mss')->nullable();
            $table->boolean('default')->default(false);
            $table->string('dns_server')->nullable();
            $table->text('on_down')->nullable();
            $table->text('on_up')->nullable();
            $table->string('only_one')->nullable();
            $table->string('use_compression')->nullable();
            $table->string('use_encryption')->nullable();
            $table->string('use_ipv6')->nullable();
            $table->string('use_mpls')->nullable();
            $table->string('use_upnp')->nullable();
            $table->unsignedBigInteger('server_id')->nullable();
            $table->timestamps();
            $table->index('name');
            $table->index('server_id'); });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_profiles');
    }
};
