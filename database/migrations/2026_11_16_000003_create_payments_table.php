<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment records, one per receipt against an order.
     *
     * Additive migration only. Subscriptions are NEVER activated from this
     * row alone — only via the verification pipeline (manual admin
     * verification or signed gateway webhook), which is what stamps
     * verified_by_admin_id / verified_at and drives activation.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('payment_method', 30)->index(); // upi | bank_transfer | gateway | other
            $table->string('transaction_reference', 100)->nullable()->index();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->dateTime('payment_date')->nullable();
            $table->string('payment_status', 20)->default('pending')->index(); // pending | paid | partially_paid | failed | refunded
            $table->string('source', 20)->default('manual'); // manual | gateway
            // Who/what verified the payment: an admin's id (manual) or the
            // signed gateway webhook. Verified only when one of these is set.
            $table->unsignedBigInteger('verified_by_admin_id')->nullable();
            $table->string('verification_source', 20)->nullable(); // admin | gateway
            $table->timestamp('verified_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('verified_by_admin_id')->references('id')->on('admins')->nullOnDelete();

            // A gateway event may never produce two payment rows for the
            // same order + reference; admin-manual rows may omit the ref.
            $table->index(['order_id', 'source', 'transaction_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
