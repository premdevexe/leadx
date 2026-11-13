<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add explicit snapshot columns to sales_orders table.
     *
     * These snapshots permanently preserve the product name, price and duration
     * at the moment of order creation, guaranteeing that future catalog edits
     * or price updates never alter historical orders.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('product_name_snapshot')->nullable()->after('product_name');
            $table->decimal('product_price_snapshot', 15, 2)->default(0)->after('product_value');
            $table->string('product_duration_snapshot', 20)->nullable()->after('duration');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn([
                'product_name_snapshot',
                'product_price_snapshot',
                'product_duration_snapshot',
            ]);
        });
    }
};
