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
        Schema::create('equipment_uses', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_id');
            $table->string('qty', 50);
            $table->string('note',)->nullable();
            $table->foreign('product_id')->references('id')->on('products')
                ->cascadeOnUpdate()->restrictOnDelete();


            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_uses');
    }
};
