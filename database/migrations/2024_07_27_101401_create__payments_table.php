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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id')->unique();
            $table->unsignedBigInteger('customer_id');
            $table->date('recieved_date');
            $table->string('client_code')->nullable();
            $table->string('recieved_by')->nullable();
            $table->string('payment_info')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            
            $table->decimal('discount', 8, 2)->default(0);
            $table->decimal('received_amount', 8, 2)->default(0);
            $table->decimal('total_amount', 8, 2)->default(0);
            $table->string('transaction_no')->nullable();
            $table->string('payment_method')->nullable();
            
            // 💳 Payment Status: ট্রানজ্যাকশন/ইনভয়েস পেমেন্টের অবস্থা
            $table->enum('payment_status', ['pending', 'paid', 'partial', 'unpaid'])->default('pending');
            
            // 🛡️ Approval Status: Super Admin এর ভেরিফিকেশন ও অ্যাপ্রুভাল লেয়ার
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            
            // 📝 Approval Tracking Fields
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->text('notes')->nullable();

            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
