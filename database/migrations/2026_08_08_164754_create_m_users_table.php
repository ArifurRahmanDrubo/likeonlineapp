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
        Schema::create('m_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id')->nullable()->index();
            $table->string('mikrotik_id', 50)->nullable()->index();
            $table->string('name')->index();
            $table->string('server_name')->index();
            $table->string('password')->nullable();
            $table->string('service', 50)->nullable();
            $table->string('profile')->nullable();
            $table->boolean('disabled')->default(false)->index();
            $table->string('caller_id')->nullable();
            $table->string('last_caller_id')->nullable();
            $table->string('last_disconnect_reason')->nullable();
            $table->timestamp('last_logged_out')->nullable();
            $table->unsignedBigInteger('limit_bytes_in')->default(0);
            $table->unsignedBigInteger('limit_bytes_out')->default(0);
            $table->text('ipv6_routes')->nullable();
            $table->text('routes')->nullable();
            $table->text('comment')->nullable();
            $table->string('user_status', 50)->default('Unique');

            $table->timestamps();

            $table->index(['server_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_users');
    }
};
