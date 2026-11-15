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
        // 1. Add minimum_closing_price and api_key_required to products
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('minimum_closing_price', 15, 2)->default(0)->after('price');
            $table->boolean('api_key_required')->default(false)->after('duration');
        });

        // 2. Add snapshots, closing approval workflow, and cancellation tracking to sales_orders
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('minimum_closing_price_snapshot', 15, 2)->default(0)->after('product_duration_snapshot');
            $table->boolean('api_key_required_snapshot')->default(false)->after('minimum_closing_price_snapshot');
            $table->string('closing_value_status', 20)->default('Accepted')->index()->after('api_key_required_snapshot');
            $table->timestamp('closing_approval_requested_at')->nullable()->after('closing_value_status');
            $table->timestamp('closing_approval_actioned_at')->nullable()->after('closing_approval_requested_at');
            $table->foreignId('closing_approval_actioned_by')->nullable()->after('closing_approval_actioned_at')->constrained('admins')->nullOnDelete();
            $table->text('closing_approval_notes')->nullable()->after('closing_approval_actioned_by');

            $table->text('cancellation_reason')->nullable()->after('closing_approval_notes');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('admins')->nullOnDelete();
        });

        // 3. Create refunds table for Phase 11
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('type', 20)->default('full'); // 'full' or 'partial'
            $table->text('reason');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('closing_approval_actioned_by');
            $table->dropColumn([
                'minimum_closing_price_snapshot',
                'api_key_required_snapshot',
                'closing_value_status',
                'closing_approval_requested_at',
                'closing_approval_actioned_at',
                'closing_approval_notes',
                'cancellation_reason',
                'cancelled_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'minimum_closing_price',
                'api_key_required',
            ]);
        });
    }
};
