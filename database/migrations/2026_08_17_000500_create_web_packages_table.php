<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `web_packages` stores the packages shown on the public website
     * (home / corporate / upcoming). Features are kept as JSON so admins can
     * add any number of feature lines without schema changes.
     */
    public function up(): void
    {
        Schema::create('web_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('price');
            $table->enum('package_type', ['home', 'corporate', 'upcoming']);
            $table->string('button_label')->default('Buy Package');
            $table->json('features')->nullable();
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_packages');
    }
};
