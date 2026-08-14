<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verify/Ensure the ISP Billing & MikroTik Sync columns exist.
     *
     * Idempotent: every column is guarded by Schema::hasColumn(), so this is
     * safe on fresh installs (columns created by the base migrations) as well
     * as existing databases where the columns may already be present.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'pending_mikrotik_sync')) {
                $table->boolean('pending_mikrotik_sync')->default(false);
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'previous_due')) {
                $table->decimal('previous_due', 10, 2)->default(0.00);
            }
            if (!Schema::hasColumn('customers', 'billingmonth')) {
                $table->string('billingmonth')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations — only drop the columns that this migration
     * itself may have added (the hasColumn guard prevents dropping columns
     * owned by earlier migrations).
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'pending_mikrotik_sync')) {
                $table->dropColumn('pending_mikrotik_sync');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'previous_due')) {
                $table->dropColumn('previous_due');
            }
            if (Schema::hasColumn('customers', 'billingmonth')) {
                $table->dropColumn('billingmonth');
            }
        });
    }
};
