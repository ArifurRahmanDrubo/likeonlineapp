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
        Schema::create('reseller_department', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mac_reseller_id')->nullable();
            $table->string('departmenttype', 255);
            $table->text('details')->nullable();
            $table->foreign('mac_reseller_id')->references('id')->on('mac_resellers')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_department');
    }
};
