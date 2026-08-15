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
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->text('praddress')->nullable();
            $table->text('paraddress')->nullable();
            $table->string('referenceby')->nullable();
            $table->string('profileimage')->nullable();
            $table->string('nidimage')->nullable();
            $table->string('registrationimage')->nullable();
            $table->string('joiningdate')->nullable();
            $table->decimal('basic_salary', 8, 2);
            $table->string('profileimage_public_id')->nullable();
            $table->string('nidimage_public_id')->nullable();
            $table->string('registrationimage_public_id')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
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
