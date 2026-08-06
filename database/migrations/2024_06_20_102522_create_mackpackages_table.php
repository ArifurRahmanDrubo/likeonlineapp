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
        Schema::create('mackpackages', function (Blueprint $table) {
            $table->id(); // Adds an auto-incrementing primary key
            $table->string('packagename', 255);
            $table->integer('Bandwith_Allowcation_MB');
            $table->text('details')->nullable();
            $table->timestamps(); // Adds created_at and updated_at columns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mackpackages');
    }
};
