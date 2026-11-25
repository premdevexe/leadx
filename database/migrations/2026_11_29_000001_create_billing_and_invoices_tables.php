<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Snapshots so financial history survives customer edits/deletes
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->text('customer_address')->nullable();

            $table->string('status', 30)->default('draft')->index(); // draft|sent|partially_paid|paid|overdue|cancelled|refunded
            $table->date('issue_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('currency', 3)->default('USD');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('discount_type', 15)->default('fixed'); // fixed|percent
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('amount_refunded', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);

            $table->string('payment_terms', 255)->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Recurring billing cycle tracking (prevents duplicate cycle invoices)
            $table->string('billing_cycle', 30)->nullable(); // monthly|quarterly|half_yearly|yearly
            $table->date('billing_cycle_start')->nullable();
            $table->date('billing_cycle_end')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('verification_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['subscription_id', 'billing_cycle_start', 'billing_cycle_end'], 'invoices_sub_cycle_idx');
        });

        // 2. Invoice Line Items
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 255);
            $table->string('duration', 50)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. Invoice Numbering Sequences
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 20)->default('INV');
            $table->string('period_key', 10)->default('ALL');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'period_key']);
        });

        // 4. Credit Notes / Adjustments (immutable ledger for paid invoices)
        Schema::create('invoice_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 50)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('type', 20)->default('credit_note'); // credit_note|debit_note|adjustment
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        // 5. Expenses
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 50)->unique();
            $table->string('category', 100)->index();
            $table->string('title', 255);
            $table->string('vendor', 255)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('expense_date')->index();
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->string('reference', 100)->nullable();
            $table->string('receipt_path', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        // 6. Extend existing payments & refunds tables to link to invoices
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('sales_orders')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->after('order_id')->constrained('invoices')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->after('verified_by_admin_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('sales_orders')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->after('order_id')->constrained('invoices')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->after('invoice_id')->constrained('payments')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('payment_id')->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['invoice_id', 'payment_id', 'customer_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['recorded_by_user_id']);
            $table->dropColumn(['invoice_id', 'recorded_by_user_id']);
        });

        Schema::dropIfExists('expenses');
        Schema::dropIfExists('invoice_adjustments');
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
