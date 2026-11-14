<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Notifications\RenewalReminderNotification;
use App\Notifications\SubscriptionActivatedNotification;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Subscription lifecycle access + lifecycle actions.
 *
 * Read access is scoped per actor exactly like orders (team → own team,
 * employee → own team or owned rows, admin → all). Renewal extends an
 * expiring/expired subscription only when a new verified payment covers
 * the renewal — a renewal with no verified payment never extends.
 */
class SubscriptionService
{
    /**
     * The query for the subscriptions an actor may see.
     */
    public function visibleTo(mixed $actor): Builder
    {
        $query = Subscription::query();

        if ($actor instanceof Team) {
            $query->where('team_id', $actor->id);
        } elseif ($actor instanceof User) {
            $query->where(function (Builder $q) use ($actor): void {
                $q->where('team_id', $actor->team_id)
                    ->orWhereHas('order', function (Builder $o) use ($actor): void {
                        $o->where('assigned_employee_id', $actor->id);
                    });
            });
        }

        // Admins (and null) see everything.

        return $query;
    }

    /**
     * Renew a subscription: requires verified payment on the order that
     * covers the full renewal amount. Idempotent within the same payment
     * window — a second renewal before the first expires is a no-op.
     */
    public function renew(Subscription $subscription): Subscription
    {
        $order = $subscription->order;

        if (! $order) {
            throw ValidationException::withMessages([
                'subscription' => 'This subscription has no resolvable order.',
            ]);
        }

        if (! in_array($subscription->status, ['active', 'expired'], true)) {
            throw ValidationException::withMessages([
                'subscription' => 'Only active or expired subscriptions can be renewed.',
            ]);
        }

        // Renewal is a fresh billing period: it must be paid in full with
        // verified payments — and there must be verified money beyond what
        // this subscription already counted, otherwise a renewal without a
        // new payment would silently extend it (idempotency guard).
        $alreadyCounted = Money::toCents($subscription->amount_paid);
        $newlyVerified = Money::toCents($order->verifiedPaymentTotal());

        if ($newlyVerified <= $alreadyCounted) {
            throw ValidationException::withMessages([
                'subscription' => 'A renewal requires new verified payment covering the amount due.',
            ]);
        }

        if (! $this->activator->policySatisfied($order)) {
            throw ValidationException::withMessages([
                'subscription' => 'A renewal requires verified payment covering the amount due.',
            ]);
        }

        if ($subscription->status === 'expired') {
            throw ValidationException::withMessages([
                'subscription' => 'Expired subscriptions cannot be renewed after expiry; create a new order and payment instead.',
            ]);
        }

        $anchor = now();
        $plan = $subscription->plan;
        $expiry = $this->billing->expiry($order, $plan, $anchor);

        $subscription->forceFill([
            'status' => 'active',
            'started_at' => $anchor,
            'expires_at' => $expiry,
            'renewal_date' => $this->billing->renewalDate($expiry),
            'amount_paid' => $order->verifiedPaymentTotal(),
            'last_renewal_reminder_at' => $anchor,
            // New cycle, new expiry: freeze the governing duration again.
            'duration_snapshot' => $plan?->duration_code ?? (string) ($order->duration ?: '1y'),
        ])->save();

        $order->forceFill(['order_status' => 'active'])->save();

        $team = Team::find($subscription->team_id);
        $team?->notifyNow(new SubscriptionActivatedNotification($subscription->fresh()));

        // A renewal moves the expiry, so the calendar event must follow (or be
        // created if it had never synced). Never blocks the renewal itself.
        $this->calendar->syncUpdate($subscription->fresh());

        return $subscription->fresh();
    }

    /**
     * Cancel a subscription (voluntary; no payment required). An active
     * subscription cancels immediately; a pending one just stays closed.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        if (in_array($subscription->status, ['cancelled', 'expired'], true)) {
            throw ValidationException::withMessages([
                'subscription' => 'This subscription is already '. $subscription->status .' and cannot be cancelled.',
            ]);
        }

        $subscription->forceFill(['status' => 'cancelled'])->save();

        if ($subscription->order) {
            $subscription->order->forceFill(['order_status' => 'cancelled'])->save();
        }

        // Cancellation removes the remote expiry-reminder event (idempotent:
        // no-op when none was ever created). Never blocks the cancel.
        $this->calendar->syncCancel($subscription->fresh());

        return $subscription->fresh();
    }

    /**
     * Fire due renewal reminders once per window (tracked on the row so
     * the scheduled job is repeat-safe).
     */
    public function sendDueReminders(): int
    {
        $days = (int) config('leadx.billing.renewal_reminder_days', 7);
        $sent = 0;

        Subscription::renewalDue($days)
            ->where(fn ($q) => $q->whereNull('last_renewal_reminder_at')
                ->orWhere('last_renewal_reminder_at', '<=', now()->subDays($days)))
            ->each(function (Subscription $subscription) use (&$sent): void {
                $team = $subscription->team;

                if ($team) {
                    $team->notifyNow(new RenewalReminderNotification($subscription));
                }

                $subscription->forceFill(['last_renewal_reminder_at' => now()])->save();
                $sent++;
            });

        return $sent;
    }

    public function __construct(
        protected BillingRules $billing,
        protected SubscriptionActivator $activator,
        protected \App\Services\Google\SubscriptionCalendarService $calendar,
    ) {
    }
}
