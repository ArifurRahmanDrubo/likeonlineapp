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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->decimal('amount', 8, 2);
            $table->string('billing_month');
            $table->decimal('discount', 8, 2)->default(0);
            $table->decimal('received_amount', 8, 2)->default(0);
            $table->decimal('advance', 8, 2)->default(0);
            $table->string('transaction_no')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['paid', 'unpaid'])->default('unpaid');
           $table->boolean('pending_mikrotik_sync')->default(false);
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
