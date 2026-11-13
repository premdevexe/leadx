<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\RenewalReminderNotification;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Console\Command;

/**
 * Fire the two reminder classes:
 *  - upcoming expiry  (active, expiring within the configured window)
 *  - renewal due      (active/expired, renewal date inside the window)
 *
 * Each subscription tracks its last reminder so the daily run never
 * re-notifies inside the same window. Safe to run repeatedly.
 *
 * Scheduled daily: `subscriptions:send-reminders` in routes/console.php
 */
class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:send-reminders';

    protected $description = 'Send upcoming-expiry and renewal-due notifications for active subscriptions';

    public function handle(): int
    {
        $expiryDays = (int) config('leadx.billing.expiry_reminder_days', 7);
        $renewalDays = (int) config('leadx.billing.renewal_reminder_days', 7);

        $expiring = 0;
        $renewal = 0;

        // 1. Upcoming expiry.
        Subscription::expiringSoon($expiryDays)
            ->where(fn ($q) => $q->whereNull('last_expiry_reminder_at')
                ->orWhere('last_expiry_reminder_at', '<=', now()->subDays($expiryDays)))
            ->chunk(100, function ($batch) use (&$expiring): void {
                foreach ($batch as $subscription) {
                    $subscription->team?->notifyNow(new SubscriptionExpiringNotification($subscription));
                    $subscription->forceFill(['last_expiry_reminder_at' => now()])->save();
                    $expiring++;
                }
            });

        // 2. Renewal due.
        Subscription::renewalDue($renewalDays)
            ->where(fn ($q) => $q->whereNull('last_renewal_reminder_at')
                ->orWhere('last_renewal_reminder_at', '<=', now()->subDays($renewalDays)))
            ->chunk(100, function ($batch) use (&$renewal): void {
                foreach ($batch as $subscription) {
                    $subscription->team?->notifyNow(new RenewalReminderNotification($subscription));
                    $subscription->forceFill(['last_renewal_reminder_at' => now()])->save();
                    $renewal++;
                }
            });

        $this->info("Sent {$expiring} upcoming-expiry and {$renewal} renewal-due reminder(s).");

        return self::SUCCESS;
    }
}
