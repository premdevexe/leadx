<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\SalesOrder;
use Illuminate\Support\Carbon;

/**
 * Expiry / renewal date calculation for subscriptions.
 *
 * The billing cycle always wins: a plan's cycle is used when present,
 * otherwise the order's duration code is mapped to a cycle via
 * config(leadx.billing.duration_map). Day-based durations (3d–30d) stay
 * day-accurate; cycle-based durations (3m/6m/1y) and monthly/quarterly
 * plans are computed as whole calendar periods.
 */
class BillingRules
{
    /**
     * Resolve the billing cycle for an order, given its plan (nullable).
     */
    public function cycleFor(SalesOrder $order, ?Plan $plan = null): string
    {
        $planCycle = $plan?->billing_cycle;

        if (in_array($planCycle, Plan::CYCLES, true)) {
            return $planCycle;
        }

        $map = config('leadx.billing.duration_map', []);
        $duration = (string) ($order->duration ?: '1y');

        return $map[$duration] ?? 'yearly';
    }

    /**
     * The expiry datetime for a subscription starting at $from.
     */
    public function expiry(SalesOrder $order, ?Plan $plan, ?Carbon $from = null): Carbon
    {
        $from ??= now();
        $cycle = $this->cycleFor($order, $plan);
        $duration = (string) ($order->duration ?: '1y');

        return match ($cycle) {
            'monthly' => $this->monthly($duration, $from),
            'quarterly' => $from->copy()->addMonthsNoOverflow(3),
            default => $from->copy()->addMonthsNoOverflow(12), // yearly
        };
    }

    /**
     * Renewal date = the day before expiry (the next billing period is due
     * on the last day of the current one).
     */
    public function renewalDate(Carbon $expiry): Carbon
    {
        return $expiry->copy()->subDay()->endOfDay();
    }

    /**
     * Monthly cycle: day-accurate durations stay day-accurate (adding days
     * can never overflow a month); anything else rolls a full calendar
     * month, clamped for short months (e.g. Jan 31 + 1 month = Feb 28/29).
     */
    protected function monthly(string $duration, Carbon $from): Carbon
    {
        return match ($duration) {
            '3d' => $from->copy()->addDays(3),
            '7d' => $from->copy()->addDays(7),
            '15d' => $from->copy()->addDays(15),
            '30d' => $from->copy()->addDays(30),
            default => $from->copy()->addMonthsNoOverflow(1),
        };
    }

    /**
     * Whether $from falls within the reminder window for $expiry.
     */
    public function withinExpiryWindow(Carbon $expiry, int $days): bool
    {
        return $expiry->lessThanOrEqualTo(now()->addDays($days)) && $expiry->greaterThan(now());
    }
}
