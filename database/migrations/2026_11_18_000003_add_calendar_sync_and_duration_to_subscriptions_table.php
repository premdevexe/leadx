<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: give subscriptions their own Google Calendar bookkeeping and a
 * frozen duration snapshot.
 *
 * - duration_snapshot: the order's duration code at purchase time, frozen so
 *   later catalog edits (a product's duration changing) never rewrite the
 *   horizon of an already-activated subscription. The live expiry math still
 *   prefers the order's saved snapshot; this column makes the subscription
 *   self-describing even if the order is mutated later.
 * - calendar_sync_status / message / synced_at / google_event_id: mirror the
 *   follow-up calendar columns so a subscription's expiry-reminder event has
 *   the same idempotency + retry story (reused event id on retry, pending
 *   when no connected calendar exists yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('duration_snapshot', 20)->nullable()->after('expires_at');

            $table->string('google_event_id', 255)->nullable()->index()->after('duration_snapshot');
            // synced | pending | not_connected | sync_error
            $table->string('calendar_sync_status', 20)->nullable()->index()->after('google_event_id');
            $table->string('calendar_sync_message', 500)->nullable()->after('calendar_sync_status');
            $table->timestamp('calendar_synced_at')->nullable()->after('calendar_sync_message');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'duration_snapshot',
                'google_event_id',
                'calendar_sync_status',
                'calendar_sync_message',
                'calendar_synced_at',
            ]);
        });
    }
};
