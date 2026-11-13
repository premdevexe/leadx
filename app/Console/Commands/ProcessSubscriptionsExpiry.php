<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpiredNotification;
use Illuminate\Console\Command;

/**
 * Flip lapsed subscriptions to `expired` and notify their teams.
 * Safe to run repeatedly: each run only touches subscriptions whose
 * expires_at is in the past and whose status is still `active`.
 *
 * Scheduled daily:
 *   ->hourly() or ->daily() in routes/console.php
 */
class ProcessSubscriptionsExpiry extends Command
{
    protected $signature = 'subscriptions:process-expiry';

    protected $description = 'Expire lapsed active subscriptions and notify their teams';

    public function handle(): int
    {
        $count = 0;

        Subscription::expired()->chunk(100, function ($expired) use (&$count): void {
            foreach ($expired as $subscription) {
                $subscription->forceFill(['status' => 'expired'])->save();
                $subscription->team?->notifyNow(new SubscriptionExpiredNotification($subscription));
                $count++;
            }
        });

        $this->info("Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}
