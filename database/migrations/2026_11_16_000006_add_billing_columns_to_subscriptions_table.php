<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: enrich subscriptions with the Phase 07 billing fields.
     *
     * - customer_id / product_id / plan_id: identity of what was sold
     * - billing_cycle: monthly | quarterly | yearly
     * - plan_snapshot / price_snapshot: historical price + plan snapshot,
     *   frozen at activation so later catalog edits never rewrite history
     * - renewal_date: the date the next billing cycle is due
     * - amount_paid: DECIMAL(15,2), total verified amount attributed to this
     *   subscription
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('team_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->string('billing_cycle', 20)->default('yearly')->after('plan_id');
            $table->string('plan_snapshot', 100)->nullable();
            $table->decimal('price_snapshot', 15, 2)->nullable();
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->timestamp('renewal_date')->nullable()->index();
            // Reminder tracking so scheduled jobs do not re-notify daily.
            $table->timestamp('last_renewal_reminder_at')->nullable();
            $table->timestamp('last_expiry_reminder_at')->nullable();
        });

        // Soft FKs (no hard constraints) so legacy subscription rows keep
        // working while their customers/products/plans are backfilled later.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn([
                'customer_id', 'product_id', 'plan_id', 'billing_cycle',
                'plan_snapshot', 'price_snapshot', 'amount_paid', 'renewal_date',
            ]);
        });
    }
};
