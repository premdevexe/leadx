<?php

namespace App\Console\Commands;

use App\Services\SubscriptionExpiryNotificationService;
use Illuminate\Console\Command;

/**
 * Team-only "expires tomorrow" reminder (Section 5 of the delivery spec).
 *
 * Runs daily (scheduled at the configured notification time). It selects
 * active subscriptions whose expiry date is exactly tomorrow in the app
 * timezone, and notifies only the active members of each subscription's own
 * team. Delivery is exactly once per expiry cycle (guarded by the unique
 * (subscription_id, expiry_at) index), so the job is safe to run repeatedly
 * and does not depend on a browser staying open.
 *
 * Scheduled: `subscriptions:send-team-expiry-reminders` in routes/console.php
 */
class SendTeamExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-team-expiry-reminders';

    protected $description = 'Notify active team members of subscriptions expiring tomorrow (once per cycle)';

    public function handle(SubscriptionExpiryNotificationService $service): int
    {
        $due = $service->expiringTomorrow();

        if ($due->isEmpty()) {
            $this->info('No subscriptions expire tomorrow.');

            return self::SUCCESS;
        }

        $notified = 0;

        foreach ($due as $subscription) {
            $recipients = $service->notifyIfDue($subscription);
            $notified += $recipients;

            if ($recipients > 0) {
                $this->line("Subscription #{$subscription->id} ({$subscription->order?->order_number}): reminded {$recipients} team member(s).");
            }
        }

        $this->info("Checked {$due->count()} subscription(s) expiring tomorrow; reminded {$notified} recipient(s).");

        return self::SUCCESS;
    }
}
