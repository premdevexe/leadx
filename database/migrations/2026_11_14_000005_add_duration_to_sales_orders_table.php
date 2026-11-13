<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the subscription duration to sales_orders.
     *
     * Values: 3d, 7d, 15d, 30d, 3m, 6m, 1y (see SalesOrder::DURATIONS).
     * Nullable + default '1y' so existing orders keep their prior behaviour
     * (activation was always a 1-year subscription).
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('duration', 20)->default('1y')->after('subscription_plan');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
