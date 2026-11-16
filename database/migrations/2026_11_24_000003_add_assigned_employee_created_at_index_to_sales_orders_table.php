<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a composite index on (assigned_employee_id, created_at) to sales_orders.
     * Justified by high-frequency query patterns in SalesAnalyticsService::applyFilters,
     * Employee\DashboardController, and recent orders queries that filter by assigned employee
     * and sort/filter by created_at.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index(['assigned_employee_id', 'created_at'], 'sales_orders_employee_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex('sales_orders_employee_created_idx');
        });
    }
};
