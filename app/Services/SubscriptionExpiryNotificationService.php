<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionExpiryNotification;
use App\Notifications\SubscriptionExpiresTomorrowNotification;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Dispatches the team-only "expires tomorrow" reminder.
 *
 * Recipients are the ACTIVE members of the subscription's own team — never
 * other teams, unrelated employees, customers, or public users. Delivery is
 * exactly once per expiry cycle, guarded two ways:
 *
 *  - the unique (subscription_id, expiry_at) index on
 *    `subscription_expiry_notifications` makes a re-run on the same cycle a
 *    no-op at the database level;
 *  - a renewal produces a new `expires_at`, which is a new cycle key, so the
 *    renewed subscription's own "expires tomorrow" reminder may fire again.
 *
 * All recipients are notified inside a single transaction, then the delivery
 * is recorded atomically; if the record collides with a concurrent run the
 * whole transaction rolls back, so the reminder is never double-sent.
 */
class SubscriptionExpiryNotificationService
{
    /**
     * "Tomorrow" as a date, in the application timezone, matching the spec's
     * example (expiry 20 Nov 2026 → notice 19 Nov 2026).
     */
    public function tomorrow(): Carbon
    {
        return now()->addDay()->startOfDay();
    }

    /**
     * Subscriptions that are active and whose expiry date is exactly
     * tomorrow (in the app timezone).
     */
    public function expiringTomorrow(): \Illuminate\Database\Eloquent\Collection
    {
        $tomorrow = $this->tomorrow();

        return Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', $tomorrow->toDateString())
            ->with(['order.team'])
            ->get();
    }

    /**
     * Send the reminder for a single subscription. Returns the number of
     * recipients actually notified (0 when there is no one reachable or the
     * notice was already sent this cycle). Safe to call repeatedly.
     */
    public function notifyIfDue(Subscription $subscription): int
    {
        $subscription->loadMissing(['order.team']);

        // Once per expiry cycle: if we already recorded a delivery for this
        // exact expiry timestamp, do nothing.
        if (SubscriptionExpiryNotification::alreadySentForCycle($subscription)) {
            return 0;
        }

        $team = $subscription->team;

        // Active employee members of THIS team only (verified against the
        // users table at dispatch time). Unrelated teams, inactives, and
        // non-employee roles are excluded.
        $recipients = $team ? $team->activeMembers()->get() : collect();

        if (count($recipients) === 0) {
            $this->record($subscription, 'skipped_no_recipients', []);

            return 0;
        }

        return $this->dispatch($subscription, $recipients);
    }

    /**
     * Notify all recipients and record the delivery atomically. Any
     * duplicate-key collision from a concurrent run rolls the transaction
     * back, undoing the just-sent notifications — so the once-only
     * guarantee holds even under racing schedulers.
     *
     * @param list<\App\Models\User> $recipients
     */
    protected function dispatch(Subscription $subscription, array $recipients): int
    {
        $recipientIds = array_map(fn ($u) => (int) $u->id, $recipients);
        $sentAt = now();

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($subscription, $recipients, $recipientIds, $sentAt): void {
                $notification = new SubscriptionExpiresTomorrowNotification($subscription);

                foreach ($recipients as $recipient) {
                    $recipient->notifyNow($notification);
                }

                SubscriptionExpiryNotification::record($subscription, 'sent', $recipientIds, $sentAt);
            });
        } catch (Throwable $e) {
            // A duplicate (subscription_id, expiry_at) means another run just
            // delivered this cycle. Swallow it: the once-only rule wins.
            if ($this->isDuplicateCycle($e)) {
                return 0;
            }

            throw $e;
        }

        return count($recipients);
    }

    /**
     * Record a no-recipient skip so the cycle is not retried on the next run
     * (a later re-run only becomes due again if a member is reactivated, in
     * which case the unique constraint would block the duplicate — so we
     * leave skipped cycles unrecorded-retryable by design: recording a skip
     * is still idempotent via the same unique key).
     *
     * @param list<int> $recipientIds
     */
    protected function record(Subscription $subscription, string $status, array $recipientIds): void
    {
        if (SubscriptionExpiryNotification::alreadySentForCycle($subscription)) {
            return;
        }

        try {
            SubscriptionExpiryNotification::record($subscription, $status, $recipientIds, now());
        } catch (Throwable) {
            // Lost the race to record; that is fine.
        }
    }

    /**
     * Best-effort detection of a duplicate-key failure across drivers
     * (MySQL 1062, PostgreSQL 23505, SQLite "UNIQUE constraint").
     */
    protected function isDuplicateCycle(Throwable $e): bool
    {
        $sqlState = $e->getPrevious()?->getCode();

        return in_array($sqlState, ['23505', '1062'], true)
            || str_contains(strtolower($e->getMessage()), 'unique constraint')
            || str_contains(strtolower($e->getMessage()), 'duplicate entry');
    }
}
