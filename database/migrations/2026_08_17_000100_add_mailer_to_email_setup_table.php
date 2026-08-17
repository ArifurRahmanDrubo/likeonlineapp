<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the mailer transport column to the existing email_setup table so the
     * runtime mail configuration can drive which transport Laravel uses.
     */
    public function up(): void
    {
        Schema::table('email_setup', function (Blueprint $table) {
            $table->string('mailer')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_setup', function (Blueprint $table) {
            $table->dropColumn('mailer');
        });
    }
};
