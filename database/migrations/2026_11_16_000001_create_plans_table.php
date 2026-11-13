<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subscription plans. A plan binds a product to a billing cycle and a
     * price. Orders/subscriptions snapshot the plan name, price and cycle
     * at activation time (see Subscription::plan_snapshot /
     * price_snapshot), so later edits never rewrite history.
     *
     * `duration_code` optionally overrides the product's default duration
     * for expiry calculation (values from SalesOrder::DURATIONS); when
     * null the product's duration is used.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('billing_cycle', 20)->default('yearly')->index(); // monthly | quarterly | yearly
            $table->string('duration_code', 20)->nullable(); // SalesOrder::DURATIONS key
            $table->decimal('price', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
