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

                // Section 5: customer expiry notice + admin expiry alert.
                // Non-blocking and admin-toggleable. The 24h dedup guard in
                // EmailService::enqueue() keeps repeated scheduler runs
                // from sending twice.
                try {
                    $order = $subscription->order;
                    $customer = $subscription->customer;
                    $customerEmail = $customer?->email ?? $order?->customer_email;

                    $context = [
                        'subscription_id' => $subscription->id,
                        'order_id'        => $subscription->order_id,
                        'customer_id'     => $customer?->id ?? $subscription->customer_id,
                        'customer_name'   => $customer?->name ?? $order?->customer_name ?? 'Customer',
                        'customer_email'  => $customerEmail,
                        'team_name'       => $subscription->team?->team_name,
                        'order_number'    => $order?->order_number,
                        'product_name'    => $order?->product_name_snapshot ?? '—',
                        'expiry_date'     => $subscription->expires_at?->format('M d, Y') ?? '—',
                        'plan_name'       => $subscription->plan_snapshot ?? '—',
                        'billing_cycle'   => $subscription->billing_cycle ?? '—',
                    ];

                    if ($customerEmail) {
                        app(\App\Services\EmailService::class)->dispatch(
                            'subscription.expired',
                            $context + ['to_email' => $customerEmail, 'subscription' => $subscription, 'order' => $order]
                        );
                    }

                    app(\App\Services\EmailService::class)->dispatch(
                        'admin.subscription.expiry',
                        $context + ['subscription' => $subscription, 'order' => $order]
                    );
                } catch (\Throwable) {
                    // Email failure must never block the expiry sweep.
                }

                $count++;
            }
        });

        $this->info("Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}
