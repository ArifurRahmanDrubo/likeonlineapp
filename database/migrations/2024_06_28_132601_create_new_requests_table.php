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
        Schema::create('new_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
              $table->unsignedBigInteger('user_id')->nullable();
            $table->string('occupation')->nullable();
            $table->text('remarks')->nullable();
            $table->string('profileimage')->nullable();
            $table->string('nid')->nullable();
            $table->string('nidimage')->nullable();
            $table->string('gender')->nullable();
            $table->string('dateofbirth')->nullable();
            $table->string('registrationno')->nullable();
            $table->string('registrationimage')->nullable();
            $table->string('fathername')->nullable();
            $table->string('mothername')->nullable();
            $table->string('facebook')->nullable();
            $table->string('linkidn')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('district')->nullable();
            $table->string('upzila')->nullable();
            $table->string('roadnumber')->nullable();
            $table->string('housenumber')->nullable();
            $table->string('praddress')->nullable();
            $table->string('paraddress')->nullable();
            $table->string('subzone')->nullable();
            $table->string('zone')->nullable();
            $table->string('connectiontype')->nullable();
            $table->string('package')->nullable();
            $table->string('referencecontact')->nullable();
            $table->string('clienttype')->nullable();
            $table->string('commiteddate')->nullable();
            $table->string('referenceby')->nullable();
            $table->string('createdby')->nullable();
            $table->string('profileimage_public_id')->nullable()->after('profileimage');
            $table->string('nidimage_public_id')->nullable()->after('nidimage');
            $table->string('registrationimage_public_id')->nullable()->after('registrationimage');
            $table->json('assign_to')->nullable();
            $table->string('status')->nullable()->default('Pending');
            $table->string('billingstatus')->nullable();
            $table->decimal('monthlybill', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('new_requests');
    }
};
