<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\EmailReminderTracking;
use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Send subscription expiry and renewal reminder emails per the
 * Admin-configured schedule (Admin Settings → Email Reminders).
 *
 * Uses the existing expiry calculation (Subscription::scopeExpiringSoon
 * / scopeRenewalDue + the subscription's stored dates) — no second
 * expiry system is introduced.
 *
 * Exactly-once delivery per subscription, per reminder window, PER expiry
 * cycle: the unique (subscription_id, type, days_before, cycle_at) index on
 * email_reminder_tracking makes a repeated scheduler run a no-op within a
 * cycle. cycle_at anchors the subscription's current expiry cycle
 * (expires_at for expiry reminders, renewal_date for renewal reminders),
 * so a renewed subscription — which gets a fresh expires_at — can have the
 * new cycle's windows fire again.
 *
 * Scheduled: `email:subscription-reminders` in routes/console.php.
 */
class SendEmailSubscriptionReminders extends Command
{
    protected $signature = 'email:subscription-reminders {--now : Skip the configured-time gate (manual runs and tests)}';
    protected $description = 'Send configured expiry/renewal reminder emails for subscriptions';

    public function handle(): int
    {
        $config = Setting::emailReminders();

        if (! $config['enabled']) {
            $this->info('Expiry emails are disabled in admin settings; nothing to do.');

            return self::SUCCESS;
        }

        // The scheduler ticks hourly; only act when the admin-configured
        // reminder time has passed today (string compare is safe because
        // the validated time is always zero-padded HH:MM). Later ticks
        // are no-ops via the email_reminder_tracking unique index.
        // `--now` bypasses the gate for manual runs and tests.
        if (! $this->option('now') && now()->format('H:i') < $config['reminder_time']) {
            $this->info('Reminder time '.$config['reminder_time'].' not reached yet; skipping.');

            return self::SUCCESS;
        }

        $days = $config['reminder_days'];

        $sent = 0;

        foreach ($days as $daysBefore) {
            $sent += $this->sendExpiryReminders($daysBefore, $config);
            $sent += $this->sendRenewalReminders($daysBefore, $config);
        }

        $this->info("Checked reminder windows ".implode(', ', $days)."; queued {$sent} email(s).");

        return self::SUCCESS;
    }

    /**
     * Customer-facing expiry reminders: fire when a subscription expires
     * within the given window and this window hasn't been sent yet for
     * this expiry cycle.
     */
    protected function sendExpiryReminders(int $daysBefore, array $config): int
    {
        // Reuse the existing upcoming-expiry scope so the email system
        // and the in-app reminder system agree on what "expiring" means.
        $query = Subscription::expiringSoon($daysBefore);

        return $this->dispatchForBatch(
            $query,
            'expiry',
            'subscription.expiring',
            $daysBefore,
            $config,
        );
    }

    /**
     * Customer-facing renewal reminders: fire when the renewal date is
     * inside the window.
     */
    protected function sendRenewalReminders(int $daysBefore, array $config): int
    {
        $query = Subscription::renewalDue($daysBefore);

        return $this->dispatchForBatch(
            $query,
            'renewal',
            'subscription.renewed',
            $daysBefore,
            $config,
        );
    }

    /**
     * Walk one reminder window, skipping every subscription that already
     * has a tracking row for this (type, days_before, cycle) triple. Sends
     * the customer email when enabled, and the team follow-up email when
     * the team toggle is on.
     */
    protected function dispatchForBatch(
        \Illuminate\Database\Eloquent\Builder $query,
        string $type,
        string $customerEvent,
        int $daysBefore,
        array $config,
    ): int
    {
        $sent = 0;

        $query->chunk(100, function ($batch) use ($type, $customerEvent, $daysBefore, $config, &$sent): void {
            foreach ($batch as $subscription) {
                // Cycle anchor: which expiry cycle this reminder belongs to.
                // Expiry reminders key off expires_at; renewal reminders key
                // off renewal_date. A renewed subscription gets a fresh
                // anchor, so its next cycle's windows fire again.
                $cycleAt = $type === 'renewal'
                    ? $subscription->renewal_date
                    : $subscription->expires_at;

                if ($cycleAt === null) {
                    // No date to anchor a cycle to; nothing to remind about.
                    continue;
                }

                // Atomically claim the (subscription, type, window, cycle)
                // tracking row BEFORE sending: two overlapping scheduler runs
                // both claim, and exactly one wins. The loser skips instead of
                // double-sending (a lost claim surfaces as a unique-key hit,
                // which firstOrCreate swallows and reports via 'created').
                $claimed = $this->claim($subscription, $type, $daysBefore, $cycleAt);

                if (! $claimed['created']) {
                    // Another run already owns this window — idempotent no-op.
                    continue;
                }

                $order = $subscription->order;
                $customer = $subscription->customer;
                $customerEmail = $customer?->email ?? $order?->customer_email;

                if (! $customerEmail) {
                    $claimed['row']->update([
                        'status' => 'skipped',
                        'skip_reason' => 'no_customer_email',
                    ]);

                    continue;
                }

                $context = [
                    'subscription_id'  => $subscription->id,
                    'order_id'         => $subscription->order_id,
                    'customer_id'      => $customer?->id,
                    'customer_name'    => $customer?->name ?? $order?->customer_name ?? 'Customer',
                    'customer_email'   => $customerEmail,
                    'team_name'        => $subscription->team?->team_name,
                    'order_number'     => $order?->order_number,
                    'product_name'     => $order?->product_name_snapshot ?? '—',
                    'expiry_date'      => $subscription->expires_at?->format('M d, Y') ?? '—',
                    'renewal_date'     => $subscription->renewal_date?->format('M d, Y') ?? '—',
                    'billing_cycle'    => $subscription->billing_cycle ?? '—',
                    'plan_name'        => $subscription->plan_snapshot ?? '—',
                    'days_before'      => $daysBefore,
                ];

                if ($config['notify_customer']) {
                    $log = app(\App\Services\EmailService::class)->dispatch($customerEvent, $context + ['to_email' => $customerEmail]);

                    if ($log) {
                        $sent++;
                    }
                }

                if ($config['notify_team']) {
                    $teamEvent = $type === 'expiry' ? 'team.subscription.expiring' : 'team.renewal.followup';
                    $log = app(\App\Services\EmailService::class)->dispatch($teamEvent, $context);

                    if ($log) {
                        $sent++;
                    }
                }

                $claimed['row']->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        });

        return $sent;
    }

    /**
     * Atomically claim the tracking row for this (subscription, type, window,
     * cycle) before any email is sent.
     *
     * Two overlapping scheduler runs can both reach the same subscription in
     * the same window; the unique email_reminder_cycle_uniq index guarantees
     * exactly one insert wins, so only the winner sends. A concurrent loser
     * hits the unique key on its insert — firstOrCreate surfaces that as a
     * "not created" result instead of a fatal QueryException, so overlapping
     * runs stay idempotent and the batch loop never aborts mid-chunk.
     *
     * @return array{created: bool, row: EmailReminderTracking}
     */
    protected function claim(
        Subscription $subscription,
        string $type,
        int $daysBefore,
        \DateTimeInterface $cycleAt,
    ): array {
        $search = [
            'subscription_id' => $subscription->id,
            'type' => $type,
            'days_before' => $daysBefore,
            'cycle_at' => $cycleAt,
        ];

        try {
            $row = EmailReminderTracking::firstOrCreate($search, [
                'scheduled_at' => now(),
                'status' => 'pending',
            ]);

            // firstOrCreate reports created=false when the unique index
            // already owned this row.
            return ['created' => $row->wasRecentlyCreated, 'row' => $row];
        } catch (QueryException $e) {
            if (! $e->isUniqueConstraintViolation()) {
                throw $e;
            }

            // Lost the insert race to a concurrent scheduler run — that run
            // now owns the window; treat it as a no-op instead of aborting
            // the whole chunk.
            $row = EmailReminderTracking::where($search)->first() ?? new EmailReminderTracking($search);

            return ['created' => false, 'row' => $row];
        }
    }
}
