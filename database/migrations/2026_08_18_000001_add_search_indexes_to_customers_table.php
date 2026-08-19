<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Columns used by ClientList search (server-side LIKE on text columns)
            $table->index('name');
            $table->index('mobile');
            $table->index('zone');
            $table->index('clienttype');
            $table->index('connectiontype');
            $table->index('package');
            $table->index('profile');
            $table->index('server');
            $table->index('billingstatus');
            $table->index('monthlybill');
            $table->index('praddress');

            // Columns used by BillingList dropdown filters
            $table->index('server_id');
            $table->index('status');

            // Composite index for common filter combinations
            $table->index(['status', 'zone']);
            $table->index(['status', 'package']);
            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['mobile']);
            $table->dropIndex(['zone']);
            $table->dropIndex(['clienttype']);
            $table->dropIndex(['connectiontype']);
            $table->dropIndex(['package']);
            $table->dropIndex(['profile']);
            $table->dropIndex(['server']);
            $table->dropIndex(['billingstatus']);
            $table->dropIndex(['monthlybill']);
            $table->dropIndex(['praddress']);
            $table->dropIndex(['server_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'zone']);
            $table->dropIndex(['status', 'package']);
            $table->dropIndex(['server_id', 'status']);
        });
    }
};
