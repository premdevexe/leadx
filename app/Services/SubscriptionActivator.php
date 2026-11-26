<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Subscription;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The activation decision engine.
 *
 * A subscription is created/activated ONLY when the order's verified
 * payment total satisfies the configured payment policy:
 *   - `activate_on: paid`    - verified total must reach the amount due
 *   - `activate_on: partial` - verified total may be below the due, but
 *                              only if it meets `minimum_verified_percent`
 *
 * Multi-product orders create and activate one subscription per line item
 * (`order_item_id`), each with its own duration snapshot and expiry date.
 * Legacy orders without `sales_order_items` keep a single order-level
 * subscription (`order_item_id = null`).
 */
class SubscriptionActivator
{
    public function __construct(
        protected BillingRules $billing,
        protected \App\Services\Google\SubscriptionCalendarService $calendar,
    ) {
    }

    /**
     * Attempt to activate (or leave pending) subscriptions for an order whose
     * payments have just been verified. Returns the primary (first) active
     * subscription when the policy is satisfied, or null otherwise.
     */
    public function maybeActivate(SalesOrder $order, ?int $adminId = null, ?Customer $customer = null): ?Subscription
    {
        if (in_array($order->order_status, ['cancelled', 'suspended'], true)) {
            return null;
        }

        if ($order->team_id === null) {
            return null;
        }

        if (in_array($order->closing_value_status, ['Requested', 'Rejected'], true)) {
            return null;
        }

        $requiresApiKey = $order->items()->where('api_key_required_snapshot', true)->exists()
            || ($order->items()->count() === 0 && (bool) $order->api_key_required_snapshot);

        if ($requiresApiKey && ! OrderCredentialService::hasKey($order)) {
            return null;
        }

        $policyMet = $this->policySatisfied($order);
        $items = $order->items()->get();

        /** @var Collection<int, Subscription> $activated */
        $activated = DB::transaction(function () use ($order, $items, $policyMet, $customer, $adminId) {
            $subscriptions = collect();

            if ($items->isEmpty()) {
                // Legacy single-order path (no sales_order_items rows)
                $subscription = $this->findOrCreateFor($order);

                if (! $policyMet) {
                    if ($subscription->status !== 'cancelled' && $subscription->status !== 'suspended') {
                        $subscription->status = 'pending';
                    }
                    $subscription->save();

                    return collect();
                }

                $this->activate($subscription, $order, $customer, $adminId);
                $subscriptions->push($subscription->fresh());

                return $subscriptions;
            }

            foreach ($items as $idx => $item) {
                $subscription = $this->findOrCreateForItem($order, $item, $idx === 0);

                if (! $policyMet) {
                    if ($subscription->status !== 'cancelled' && $subscription->status !== 'suspended') {
                        $subscription->status = 'pending';
                    }
                    $subscription->save();
                    continue;
                }

                $this->activateItem($subscription, $order, $item, $customer, $adminId);
                $subscriptions->push($subscription->fresh());
            }

            return $subscriptions;
        });

        if ($activated->isEmpty()) {
            return null;
        }

        foreach ($activated as $sub) {
            if ($sub->status === 'active') {
                $this->calendar->syncCreate($sub);

                try {
                    app(\App\Services\NotificationHubService::class)->notifySubscriptionActivated($sub);
                } catch (\Throwable) {
                }
            }
        }

        return $activated->first();
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

        if ($dueCents === 0) {
            return true;
        }

        if ($activateOn === 'partial') {
            $required = (int) floor($dueCents * $minPercent / 100);

            return $verifiedCents >= $required;
        }

        return $verifiedCents >= $dueCents;
    }

    /**
     * Legacy order-level subscription lookup/create.
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
     * Line-item subscription lookup/create (attaches an existing legacy null-item
     * row on the first item when upgrading/backfilling an existing pending order).
     */
    protected function findOrCreateForItem(SalesOrder $order, SalesOrderItem $item, bool $isPrimary): Subscription
    {
        $existing = Subscription::where('order_item_id', $item->id)->first();
        if ($existing) {
            return $existing;
        }

        if ($isPrimary) {
            $legacy = Subscription::where('order_id', $order->id)
                ->whereNull('order_item_id')
                ->first();
            if ($legacy) {
                $legacy->forceFill(['order_item_id' => $item->id])->save();

                return $legacy;
            }
        }

        return Subscription::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'team_id' => $order->team_id,
            'status' => 'pending',
            'product_id' => $item->product_id,
        ]);
    }

    protected function activate(Subscription $subscription, SalesOrder $order, ?Customer $customer, ?int $adminId): void
    {
        $plan = $this->resolvePlanForProductId($order->product_id);
        $durationCode = (string) ($order->duration ?: '1y');
        $cycle = $this->billing->cycleForDuration($durationCode, $plan);
        $start = $subscription->started_at ?? now();
        $expiry = $this->billing->expiryForDurationCode($durationCode, $plan, $start);
        $renewal = $this->billing->renewalDate($expiry);

        $priceSnapshot = $plan?->price ?? $order->product_value;
        if ((float) $priceSnapshot <= 0 && (float) $order->closing_value > 0) {
            $priceSnapshot = $order->closing_value;
        }

        $this->persistActiveSubscription(
            $subscription,
            $order,
            $customer,
            $order->product_id,
            $plan,
            $cycle,
            $priceSnapshot,
            $start,
            $expiry,
            $renewal,
            $plan?->duration_code ?? $durationCode,
            $order->product_name_snapshot ?? '—'
        );
    }

    protected function activateItem(Subscription $subscription, SalesOrder $order, SalesOrderItem $item, ?Customer $customer, ?int $adminId): void
    {
        $plan = $this->resolvePlanForProductId($item->product_id);
        $durationCode = (string) ($item->product_duration_snapshot ?: $order->duration ?: '1y');
        $cycle = $this->billing->cycleForDuration($durationCode, $plan);
        $start = $subscription->started_at ?? now();
        $expiry = $this->billing->expiryForDurationCode($durationCode, $plan, $start);
        $renewal = $this->billing->renewalDate($expiry);

        $priceSnapshot = $plan?->price ?? $item->line_total;
        if ((float) $priceSnapshot <= 0 && (float) $order->closing_value > 0) {
            $priceSnapshot = $order->closing_value;
        }

        $this->persistActiveSubscription(
            $subscription,
            $order,
            $customer,
            $item->product_id,
            $plan,
            $cycle,
            $priceSnapshot,
            $start,
            $expiry,
            $renewal,
            $plan?->duration_code ?? $durationCode,
            $item->product_name_snapshot ?? '—'
        );
    }

    protected function persistActiveSubscription(
        Subscription $subscription,
        SalesOrder $order,
        ?Customer $customer,
        ?int $productId,
        ?Plan $plan,
        string $cycle,
        mixed $priceSnapshot,
        \Illuminate\Support\Carbon $start,
        \Illuminate\Support\Carbon $expiry,
        \Illuminate\Support\Carbon $renewal,
        string $durationSnapshot,
        string $productName
    ): void {
        $subscription->forceFill([
            'customer_id' => $customer?->id ?? $subscription->customer_id,
            'product_id' => $productId,
            'plan_id' => $plan?->id ?? $subscription->plan_id,
            'billing_cycle' => $cycle,
            'plan_snapshot' => $plan?->name,
            'price_snapshot' => $priceSnapshot,
            'amount_paid' => $order->verifiedPaymentTotal(),
            'status' => 'active',
            'started_at' => $subscription->started_at ?? $start,
            'expires_at' => $expiry,
            'renewal_date' => $renewal,
            'duration_snapshot' => $durationSnapshot,
        ])->save();

        $order->forceFill(['order_status' => 'active'])->save();

        try {
            $customerEmail = $customer?->email ?? $order->customer_email;

            app(\App\Services\EmailService::class)->dispatch('subscription.activated', [
                'subscription_id'  => $subscription->id,
                'order_id'         => $order->id,
                'customer_id'      => $customer?->id ?? $order->customer_id,
                'customer_name'    => $customer?->name ?? $order->customer_name ?? 'Customer',
                'customer_email'   => $customerEmail,
                'team_name'        => $subscription->team?->team_name,
                'order_number'     => $order->order_number,
                'product_name'     => $productName,
                'plan_name'        => $plan?->name ?? '—',
                'activation_date'  => $subscription->started_at?->format('M d, Y') ?? '—',
                'expiry_date'      => $expiry->format('M d, Y'),
                'renewal_date'     => $renewal->format('M d, Y'),
                'billing_cycle'    => $cycle,
                'to_email'         => $customerEmail,
            ]);
        } catch (\Throwable) {
        }
    }

    protected function resolvePlanForProductId(?int $productId): ?Plan
    {
        if ($productId && $plan = Plan::where('product_id', $productId)->active()->orderByDesc('id')->first()) {
            return $plan;
        }

        return null;
    }

    protected function notifyCustomer(Customer $customer, SalesOrder $order): void
    {
    }
}
