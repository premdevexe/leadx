<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add an expiry-cycle anchor to email_reminder_tracking so reminders fire
 * once per window PER expiry cycle, not once ever.
 *
 * The old unique key (subscription_id, type, days_before) meant that after
 * the first lifecycle of a subscription, every later window — including
 * after a renewal that resets expires_at — was a permanent no-op. The new
 * key includes cycle_at (the subscription's expires_at for expiry reminders,
 * its renewal_date for renewal reminders), so a renewed subscription's new
 * cycle can fire again while repeated scheduler runs stay idempotent.
 *
 * Idempotent and DB-agnostic (MySQL in dev/prod, SQLite in tests): a
 * partially-applied run can still complete, because MySQL auto-commits DDL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. The column (skip if a partially-applied run already added it).
        if (! Schema::hasColumn('email_reminder_tracking', 'cycle_at')) {
            Schema::table('email_reminder_tracking', function (Blueprint $table): void {
                $table->timestamp('cycle_at')->nullable()->after('days_before');
            });
        }

        // 2. Backfill existing rows from their subscriptions so the new
        //    unique key has real cycle anchors. Multi-table UPDATE on
        //    MySQL/MariaDB; PHP loop elsewhere (test runs use SQLite).
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("
                UPDATE email_reminder_tracking t
                JOIN subscriptions s ON s.id = t.subscription_id
                SET t.cycle_at = CASE
                    WHEN t.type = 'renewal' THEN s.renewal_date
                    ELSE s.expires_at
                END
                WHERE t.cycle_at IS NULL
            ");
        } else {
            foreach (DB::table('email_reminder_tracking')->get() as $row) {
                if ($row->cycle_at !== null) {
                    continue;
                }

                $sub = DB::table('subscriptions')->where('id', $row->subscription_id)->first();
                $anchor = $sub ? ($row->type === 'renewal' ? $sub->renewal_date : $sub->expires_at) : null;

                DB::table('email_reminder_tracking')
                    ->where('id', $row->id)
                    ->update(['cycle_at' => $anchor ?? now()->subYear()]);
            }
        }

        // 3. Swap the unique index. On MySQL the old unique index backs the
        //    subscription_id foreign key, so it cannot be dropped in place:
        //    drop the FK, drop the old unique, add the new one, re-add the FK.
        $fk = collect(Schema::getForeignKeys('email_reminder_tracking'))
            ->first(fn (array $fk): bool => $fk['columns'] === ['subscription_id'] && $fk['foreign_table'] === 'subscriptions');

        if ($fk !== null) {
            Schema::table('email_reminder_tracking', function (Blueprint $table): void {
                $table->dropForeign(['subscription_id']);
            });
        }

        $indexes = collect(Schema::getIndexes('email_reminder_tracking'))
            ->pluck('name')
            ->all();

        if (in_array('email_reminder_uniq', $indexes, true)) {
            Schema::table('email_reminder_tracking', function (Blueprint $table): void {
                $table->dropUnique('email_reminder_uniq');
            });
        }

        if (! in_array('email_reminder_cycle_uniq', $indexes, true)) {
            Schema::table('email_reminder_tracking', function (Blueprint $table): void {
                $table->unique(['subscription_id', 'type', 'days_before', 'cycle_at'], 'email_reminder_cycle_uniq');
            });
        }

        if ($fk !== null) {
            Schema::table('email_reminder_tracking', function (Blueprint $table): void {
                $table->foreign('subscription_id')
                    ->references('id')->on('subscriptions')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $fk = collect(Schema::getForeignKeys('email_reminder_tracking'))
            ->first(fn (array $fk): bool => $fk['columns'] === ['subscription_id'] && $fk['foreign_table'] === 'subscriptions');

        if ($fk !== null) {
            Schema::table('email_reminder_tracking', function (Blueprint $table): void {
                $table->dropForeign(['subscription_id']);
            });
        }

        $indexes = collect(Schema::getIndexes('email_reminder_tracking'))
            ->pluck('name')
            ->all();

        if (in_array('email_reminder_cycle_uniq', $indexes, true)) {
            Schema::table('email_reminder_tracking', function (Blueprint $table): void {
                $table->dropUnique('email_reminder_cycle_uniq');
            });
        }

        // Re-creating the old 3-column unique key fails on any deployment
        // that accumulated one row per expiry cycle: multiple rows can
        // share (subscription_id, type, days_before). Collapse each group
        // to its most recent row before restoring the old index. The
        // derived-table wrapper keeps the statement valid on SQLite too.
        DB::statement("
            DELETE FROM email_reminder_tracking
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MAX(id) AS id
                    FROM email_reminder_tracking
                    GROUP BY subscription_id, type, days_before
                ) AS deduped
            )
        ");

        if (! in_array('email_reminder_uniq', $indexes, true)) {
            Schema::table('email_reminder_tracking', function (Blueprint $table): void {
                $table->unique(['subscription_id', 'type', 'days_before'], 'email_reminder_uniq');
            });
        }

        if ($fk !== null) {
            Schema::table('email_reminder_tracking', function (Blueprint $table): void {
                $table->foreign('subscription_id')
                    ->references('id')->on('subscriptions')
                    ->cascadeOnDelete();
            });
        }

        Schema::table('email_reminder_tracking', function (Blueprint $table): void {
            $table->dropColumn('cycle_at');
        });
    }
};
