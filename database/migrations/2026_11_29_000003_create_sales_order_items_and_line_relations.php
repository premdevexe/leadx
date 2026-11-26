<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Line items table for multi-product orders
        if (! Schema::hasTable('sales_order_items')) {
            Schema::create('sales_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('sales_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('product_name_snapshot')->nullable();
                $table->decimal('product_price_snapshot', 15, 2)->default(0);
                $table->string('product_duration_snapshot', 50)->nullable();
                $table->decimal('minimum_closing_price_snapshot', 15, 2)->default(0);
                $table->boolean('api_key_required_snapshot')->default(false);
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2)->default(0);
                $table->timestamps();

                $table->index('order_id');
            });
        }

        // 2. Allow per-line subscriptions while preserving legacy order_id links
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasIndex('subscriptions', 'subscriptions_order_id_unique')) {
                $table->dropForeign(['order_id']);
                $table->dropUnique(['order_id']);
            }
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasIndex('subscriptions', 'subscriptions_order_id_index')) {
                $table->index('order_id');
                $table->foreign('order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('subscriptions', 'order_item_id')) {
                $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained('sales_order_items')->cascadeOnDelete();
                $table->unique('order_item_id');
            }
        });

        // 3. Allow per-line API credentials while preserving legacy order_id links
        Schema::table('order_api_credentials', function (Blueprint $table) {
            if (Schema::hasIndex('order_api_credentials', 'order_api_credentials_order_id_unique')) {
                $table->dropForeign(['order_id']);
                $table->dropUnique(['order_id']);
            }
        });
        Schema::table('order_api_credentials', function (Blueprint $table) {
            if (! Schema::hasIndex('order_api_credentials', 'order_api_credentials_order_id_index')) {
                $table->index('order_id');
                $table->foreign('order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('order_api_credentials', 'order_item_id')) {
                $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained('sales_order_items')->cascadeOnDelete();
                $table->unique('order_item_id');
            }
        });

        // 4. Chunked backfill of one item row (quantity = 1) per existing order
        DB::table('sales_orders')
            ->orderBy('id')
            ->chunkById(200, function ($orders) {
                $rows = [];
                $now = now();
                foreach ($orders as $order) {
                    if (DB::table('sales_order_items')->where('order_id', $order->id)->exists()) {
                        continue;
                    }
                    $unitPrice = (float) ($order->product_price_snapshot ?? $order->product_value ?? 0);
                    $rows[] = [
                        'order_id' => $order->id,
                        'product_id' => $order->product_id,
                        'product_name_snapshot' => $order->product_name_snapshot ?? $order->product_name,
                        'product_price_snapshot' => $unitPrice,
                        'product_duration_snapshot' => $order->product_duration_snapshot ?? $order->duration,
                        'minimum_closing_price_snapshot' => (float) ($order->minimum_closing_price_snapshot ?? 0),
                        'api_key_required_snapshot' => (bool) ($order->api_key_required_snapshot ?? false),
                        'quantity' => 1,
                        'unit_price' => $unitPrice,
                        'line_total' => $unitPrice,
                        'created_at' => $order->created_at ?? $now,
                        'updated_at' => $order->updated_at ?? $now,
                    ];
                }
                if (! empty($rows)) {
                    DB::table('sales_order_items')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::table('order_api_credentials', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
            $table->dropUnique(['order_item_id']);
            $table->dropColumn('order_item_id');
            if (Schema::hasIndex('order_api_credentials', 'order_api_credentials_order_id_index')) {
                $table->dropForeign(['order_id']);
                $table->dropIndex(['order_id']);
                $table->unique('order_id');
                $table->foreign('order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
            $table->dropUnique(['order_item_id']);
            $table->dropColumn('order_item_id');
            if (Schema::hasIndex('subscriptions', 'subscriptions_order_id_index')) {
                $table->dropForeign(['order_id']);
                $table->dropIndex(['order_id']);
                $table->unique('order_id');
                $table->foreign('order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
            }
        });

        Schema::dropIfExists('sales_order_items');
    }
};
