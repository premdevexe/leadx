<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the order-level subscription_plan text field. It has been
     * superseded by the product-driven duration (sales_orders.duration)
     * and the Plan catalog (subscriptions.plan_id / plan_snapshot).
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('subscription_plan');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('subscription_plan', 50)->nullable()->after('product_name');
        });
    }
};
