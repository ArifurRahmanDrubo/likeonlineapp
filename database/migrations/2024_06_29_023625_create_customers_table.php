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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('server_id')->nullable();
            $table->string('name');
            $table->string('radius_id')->nullable();
            $table->string('profileimage')->nullable();
            $table->string('occupation')->nullable();
            $table->text('remarks')->nullable();
            $table->string('nid')->nullable();
            $table->string('nidimage')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('dateofbirth')->nullable();
            $table->string('registrationno')->nullable();
            $table->string('registrationimage')->nullable();
            $table->string('profileimage_public_id')->nullable()->after('profileimage');
            $table->string('nidimage_public_id')->nullable()->after('nidimage');
            $table->string('registrationimage_public_id')->nullable()->after('registrationimage');
            $table->string('fathername')->nullable();;
            $table->string('mothername')->nullable();;
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('facebook')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();;
            $table->string('district')->nullable();;
            $table->string('upzila')->nullable();;
            $table->string('roadnumber')->nullable();
            $table->string('housenumber')->nullable();
            $table->text('praddress')->nullable();
            $table->text('paraddress')->nullable();
            $table->string('server')->nullable();
            $table->string('subzone')->nullable();
            $table->string('zone');
            $table->string('protocoltype');
            $table->string('box')->nullable();
            $table->string('connectiontype');
            $table->string('cable')->nullable();
            $table->string('fiber')->nullable();
            $table->string('coreno')->nullable();
            $table->string('corecolor')->nullable();
            $table->string('package');
            $table->string('profile')->nullable();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('clienttype');
            $table->string('expireddate');
            $table->string('referenceby')->nullable();
            $table->string('connectedby')->nullable();
            // $table->string('caller-id')->nullable();
            $table->string('con_charge')->nullable();
            $table->string('joiningdate');
            $table->string('billingmonth');
            $table->string('billingstatus');
            $table->decimal('monthlybill', 8, 2);
            $table->boolean('mikrotikStatus')->default(true);
            $table->foreign('server_id')->references('id')->on('mikrotikservers')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
