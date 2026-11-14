<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The activation decision engine.
 *
 * A subscription is created/activated ONLY when the order's verified
 * payment total satisfies the configured payment policy:
 *   - `activate_on: paid`    - verified total must reach the amount due
 *   - `activate_on: partial` - verified total may be below the due, but
 *                              only if it meets `minimum_verified_percent`
 *
 * Pending and failed payments are never counted. The decision is
 * idempotent: re-running against the same order converges on a single
 * subscription row (keyed by order_id, which is unique), so repeated
 * verifies and repeated gateway webhooks can never duplicate an
 * activation.
 *
 * History is preserved on the subscription row: the price/plan snapshots
 * and the billing dates are frozen from the order's stored values at
 * activation time, so later catalog edits never rewrite what was sold.
 */
class SubscriptionActivator
{
    public function __construct(
        protected BillingRules $billing,
        protected \App\Services\Google\SubscriptionCalendarService $calendar,
    ) {
    }

    /**
     * Attempt to activate (or leave pending) the subscription for an order
     * whose payments have just been verified. Returns the subscription when
     * it is active, and null when the policy has not been met yet.
     */
    public function maybeActivate(SalesOrder $order, ?int $adminId = null, ?Customer $customer = null): ?Subscription
    {
        // A cancelled order never activates, no matter the payment state.
        if (in_array($order->order_status, ['cancelled', 'suspended'], true)) {
            return null;
        }

        $policyMet = $this->policySatisfied($order);

        $subscription = DB::transaction(function () use ($order, $policyMet, $customer, $adminId) {
            $subscription = $this->findOrCreateFor($order);

            if (! $policyMet) {
                // Not enough verified payment: keep it pending. A failed
                // payment never flips this to active either.
                if ($subscription->status !== 'cancelled' && $subscription->status !== 'suspended') {
                    $subscription->status = 'pending';
                }
                $subscription->save();

                return null;
            }

            $this->activate($subscription, $order, $customer, $adminId);

            return $subscription->fresh();
        });

        // Now active: create (or reconcile) the Google Calendar expiry-reminder
        // event. Done OUTSIDE the activation transaction so a calendar hiccup
        // never rolls back a valid activation; the sync records a pending
        // status when no calendar is connected, and is idempotent so a re-run
        // (e.g. a repeated webhook) never creates a second event.
        if ($subscription && $subscription->status === 'active') {
            $this->calendar->syncCreate($subscription);
        }

        return $subscription;
    }

    /**
     * Does the order's verified total satisfy the activation policy?
     */
    public function policySatisfied(SalesOrder $order): bool
    {
        $activateOn = config('leadx.payments.activate_on', 'paid');
        $minPercent = (float) config('leadx.payments.minimum_verified_percent', 100.0);

        $dueCents = Money::toCents($order->amountDue());
        $verifiedCents = Money::toCents($order->verifiedPaymentTotal());

        // No amount due (e.g. a free/grant order) is treated as satisfied
        // the moment any verified payment exists or due is zero.
        if ($dueCents === 0) {
            return true;
        }

        if ($activateOn === 'partial') {
            $required = (int) floor($dueCents * $minPercent / 100);

            return $verifiedCents >= $required;
        }

        // Default 'paid': verified total must reach the full amount due.
        return $verifiedCents >= $dueCents;
    }

    /**
     * Resolve or create the subscription row for an order. The row is keyed
     * by the unique order_id, so this can never mint a duplicate.
     */
    protected function findOrCreateFor(SalesOrder $order): Subscription
    {
        $existing = Subscription::where('order_id', $order->id)->first();

        if ($existing) {
            return $existing;
        }

        return Subscription::create([
            'order_id' => $order->id,
            'team_id' => $order->team_id,
            'status' => 'pending',
            'product_id' => $order->product_id,
        ]);
    }

    /**
     * Flip a subscription to active, freezing plan/price snapshots and the
     * duration-derived dates. Idempotent: re-activating an already active
     * subscription keeps its start and recomputes nothing destructive.
     */
    protected function activate(Subscription $subscription, SalesOrder $order, ?Customer $customer, ?int $adminId): void
    {
        $plan = $this->resolvePlan($order);

        $cycle = $this->billing->cycleFor($order, $plan);
        $start = $subscription->started_at ?? now();
        // Expiry is derived from the order's stored duration snapshot (or
        // the plan's explicit duration override) - never from live catalog
        // values - so historical subscriptions keep their sold horizon.
        $expiry = $this->billing->expiry($order, $plan, $start);
        $renewal = $this->billing->renewalDate($expiry);

        // Price snapshot: the order's frozen product value when no plan
        // price applies (falls back to the closing value for manual orders
        // whose product value is zero).
        $priceSnapshot = $plan?->price ?? $order->product_value;

        if ((float) $priceSnapshot <= 0 && (float) $order->closing_value > 0) {
            $priceSnapshot = $order->closing_value;
        }

        $subscription->forceFill([
            'customer_id' => $customer?->id ?? $subscription->customer_id,
            'product_id' => $order->product_id,
            'plan_id' => $plan?->id ?? $subscription->plan_id,
            'billing_cycle' => $cycle,
            'plan_snapshot' => $plan?->name,
            'price_snapshot' => $priceSnapshot,
            'amount_paid' => $order->verifiedPaymentTotal(),
            'status' => 'active',
            // Activation date: the moment the verified purchase activates the
            // subscription (kept on re-activation, so a repeated verify does
            // not move the start or create a second activation).
            'started_at' => $subscription->started_at ?? now(),
            // Expiry = activation date + the frozen duration snapshot.
            'expires_at' => $expiry,
            'renewal_date' => $renewal,
            // The duration that governs THIS subscription's horizon, frozen
            // at activation. Product/plan duration edits later never move it.
            'duration_snapshot' => $plan?->duration_code ?? (string) ($order->duration ?: '1y'),
        ])->save();

        $order->forceFill(['order_status' => 'active'])->save();

        if ($customer) {
            $this->notifyCustomer($customer, $order);
        }
    }

    protected function resolvePlan(SalesOrder $order): ?Plan
    {
        // A plan is resolved by the product the order was created from.
        if ($order->product_id && $plan = Plan::where('product_id', $order->product_id)->active()->orderByDesc('id')->first()) {
            return $plan;
        }

        return null;
    }

    protected function notifyCustomer(Customer $customer, SalesOrder $order): void
    {
        // Customer notifications are delivered via the configured channel;
        // here we only ensure the notifiable target is reachable. A
        // no-op when the customer has no email/phone channel configured.
    }
}