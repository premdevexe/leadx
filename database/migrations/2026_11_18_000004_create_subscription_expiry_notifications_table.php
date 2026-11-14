<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery log for the team-only "expires tomorrow" reminder.
 *
 * The reminder is exactly-once per expiry cycle. `expiry_at` is the
 * subscription's expiry timestamp at the moment the notice fired, and the
 * unique (subscription_id, expiry_at) index makes "renewal starts a new
 * cycle" work for free: a renewed subscription gets a new expires_at, which
 * is a different cycle key, so its one-day-before notice can fire again,
 * while a second run of the scheduler on the same day hits the unique
 * constraint and is skipped.
 *
 * `recipient_count` / `recipient_user_ids` record WHO actually got it so
 * the once-only guarantee is auditable (and provably team-members-only).
 * `sent_at` is the dispatch timestamp; `status` distinguishes a real send
 * from a run where no active team member could be reached.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_expiry_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            // The expiry cycle this notice belongs to (the expiry timestamp
            // in effect when it was sent). Part of the uniqueness key.
            $table->timestamp('expiry_at');
            // sent | skipped_no_recipients
            $table->string('status', 30)->default('sent')->index();
            $table->unsignedInteger('recipient_count')->default(0);
            // JSON array of user ids notified — the auditable recipient set.
            $table->text('recipient_user_ids')->nullable();
            $table->timestamp('sent_at');

            $table->timestamps();

            // The once-per-expiry-cycle guarantee, enforced at the database.
            $table->unique(['subscription_id', 'expiry_at']);
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_expiry_notifications');
    }
};
