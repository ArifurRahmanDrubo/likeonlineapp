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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('experience')->nullable();
            $table->string('nid')->nullable();
            $table->string('gender')->nullable();
            $table->string('dateofbirth')->nullable();
            $table->string('registrationno')->nullable();
            $table->string('fathername')->nullable();
            $table->string('mothername')->nullable();
            $table->string('maritalstatus')->nullable();
            $table->string('officeContact')->nullable();
            $table->string('facebook')->nullable();
            $table->string('guardianContact')->nullable();
            $table->string('twitter')->nullable();
            $table->string('mobile')->nullable();
            $table->string('status')->default('active');
            $table->string('degree')->nullable();
            $table->string('institute')->nullable();
            $table->string('passingYear')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('district')->nullable();
            $table->string('upzila')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->text('praddress')->nullable();
            $table->text('paraddress')->nullable();
            $table->string('referenceby')->nullable();
            $table->string('profileimage')->nullable();
            $table->string('nidimage')->nullable();
            $table->string('registrationimage')->nullable();
            $table->string('joiningdate')->nullable();
            $table->decimal('basic_salary', 8, 2);
            $table->string('profileimage_public_id')->nullable()->after('profileimage');
            $table->string('nidimage_public_id')->nullable()->after('nidimage');
            $table->string('registrationimage_public_id')->nullable()->after('registrationimage');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
