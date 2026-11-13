<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the sales_orders table.
     *
     * Additive migration only: does not touch existing teams/users data.
     * All monetary columns use DECIMAL(15,2).
     */
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            // Nullable: pre-existing (legacy) rows may exist without a number
            // until the backfill command assigns one. Uniqueness still holds
            // for all numbered rows; NULLs never collide.
            $table->string('order_number', 50)->unique()->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('team_code_snapshot', 50)->nullable();
            $table->unsignedBigInteger('assigned_employee_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone', 30)->nullable();
            $table->string('product_name')->nullable();
            $table->string('subscription_plan', 50)->nullable();
            $table->decimal('product_value', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('closing_value', 15, 2)->default(0);
            $table->decimal('amount_received', 15, 2)->default(0);
            $table->string('payment_status', 20)->default('pending')->index();
            $table->string('order_status', 20)->default('pending')->index();
            $table->string('legacy_flag', 30)->nullable();
            $table->timestamps();

            // Indexes only, no hard FKs: legacy sales_orders rows may carry
            // dangling team_id / assigned_employee_id references (deleted or
            // never-migrated teams). The backfill command flags those instead
            // of failing, and the hard FK required by the spec lives on the
            // order_number_sequences table.
            $table->index(['team_id', 'order_status']);
            $table->index('assigned_employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
