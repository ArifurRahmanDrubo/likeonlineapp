<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align the invoice_setup columns with what the Vue form, the controller
     * and the PDF blades actually use: the schema stored the phone as
     * `numbers` while every consumer reads `mobile`, and the form also edits
     * `invoice_title` which had no column at all (causing update() to throw).
     */
    public function up(): void
    {
        Schema::table('invoice_setup', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_setup', 'numbers') && !Schema::hasColumn('invoice_setup', 'mobile')) {
                $table->renameColumn('numbers', 'mobile');
            }
            if (!Schema::hasColumn('invoice_setup', 'invoice_title')) {
                $table->string('invoice_title')->nullable()->after('website');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_setup', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_setup', 'mobile') && !Schema::hasColumn('invoice_setup', 'numbers')) {
                $table->renameColumn('mobile', 'numbers');
            }
            if (Schema::hasColumn('invoice_setup', 'invoice_title')) {
                $table->dropColumn('invoice_title');
            }
        });
    }
};
