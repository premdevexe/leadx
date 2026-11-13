<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\SalesOrder;
use Illuminate\Support\Carbon;

/**
 * Expiry / renewal date calculation for subscriptions.
 *
 * Expiry is driven by the saved *duration snapshot*: when a plan carries an
 * explicit duration_code it wins, otherwise the order's own duration (frozen
 * at creation from the catalog product) decides. Day-based durations stay
 * day-accurate; month-based durations roll whole calendar periods (clamped
 * for short months).
 *
 * A plan with a billing cycle but no duration override keeps the legacy
 * cycle behaviour, so existing plan-driven subscriptions are unchanged.
 */
class BillingRules
{
    /**
     * Resolve the billing cycle for an order, given its plan (nullable).
     *
     * Retained for reporting / reminder logic. Expiry itself is computed
     * from the duration snapshot (see {@see self::expiry()}).
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
     *
     * Precedence:
     *   1. an explicit plan duration_code
     *   2. otherwise, when no plan cycle governs (no plan, or a plan
     *      without a recognized cycle) the order's saved duration snapshot
     *   3. otherwise the legacy plan-cycle behaviour is preserved
     */
    public function expiry(SalesOrder $order, ?Plan $plan, ?Carbon $from = null): Carbon
    {
        $from ??= now();
        $planDuration = $plan?->duration_code;

        $durationDriven = $planDuration !== null
            || $plan === null
            || ! in_array($plan?->billing_cycle, Plan::CYCLES, true);

        if ($durationDriven) {
            $duration = $planDuration !== null ? $planDuration : (string) ($order->duration ?: '1y');

            return $this->expiryForDuration($duration, $from);
        }

        // Legacy: a recognized plan cycle with no duration override.
        $duration = (string) ($order->duration ?: '1y');

        return match ($plan->billing_cycle) {
            'monthly' => $this->monthly($duration, $from),
            'quarterly' => $from->copy()->addMonthsNoOverflow(3),
            default => $from->copy()->addMonthsNoOverflow(12), // yearly
        };
    }

    /**
     * Expiry from a duration snapshot: day-accurate for day codes, whole
     * calendar periods for month codes, clamped for short months.
     */
    protected function expiryForDuration(string $duration, Carbon $from): Carbon
    {
        return match ($duration) {
            '3d' => $from->copy()->addDays(3),
            '7d' => $from->copy()->addDays(7),
            '15d' => $from->copy()->addDays(15),
            '30d' => $from->copy()->addDays(30),
            '3m' => $from->copy()->addMonthsNoOverflow(3),
            '6m' => $from->copy()->addMonthsNoOverflow(6),
            default => $from->copy()->addMonthsNoOverflow(12), // '1y' + unknown
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