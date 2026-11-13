<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Revenue analytics, computed only from *verified* (collected) money.
 *
 * Every figure is a DECIMAL(15,2) string produced through exact integer
 * cent arithmetic ({@see Money}); pending, failed and refunded payments
 * never count toward collected revenue.
 *
 * The product report is catalog-driven: every row in the `products`
 * table gets a column automatically (zero-filled), so a product an admin
 * creates today shows up in every report with no configuration. Orders
 * that carry a product name but no catalog reference roll up into a
 * single "Uncataloged" bucket.
 */
class SalesAnalyticsService
{
    /**
     * @return array<string, string>
     */
    public function summary(?Team $team = null, ?User $employee = null): array
    {
        $orders = $this->ordersFor($team, $employee);

        $totalProduct = 0;
        $totalClosing = 0;
        $collected = 0;

        foreach ($orders as $order) {
            $totalProduct += Money::toCents($order->product_value);
            $totalClosing += Money::toCents($order->closing_value);
            // Collected = verified payments only (see verifiedPaymentTotal).
            $collected += Money::toCents($order->verifiedPaymentTotal());
        }

        $pendingCollection = max($totalClosing - $collected, 0);

        return [
            'total_product_value' => Money::format($totalProduct),
            'total_deal_closing_value' => Money::format($totalClosing),
            'amount_received' => Money::format($collected),
            'pending_collection' => Money::format($pendingCollection),
        ];
    }

    /**
     * Per-employee sales, scoped to a team when one is given.
     *
     * @return array<int, array<string, string|int>>
     */
    public function byEmployee(?Team $team = null): array
    {
        $rows = [];

        foreach ($this->ordersFor($team) as $order) {
            $key = (int) ($order->assigned_employee_id ?? 0);
            $rows[$key]['employee_id'] = $key;

            $rows[$key]['collected'] = Money::format(Money::toCents($rows[$key]['collected'] ?? 0) + Money::toCents($order->verifiedPaymentTotal()));
            $rows[$key]['deal_value'] = Money::format(Money::toCents($rows[$key]['deal_value'] ?? 0) + Money::toCents($order->closing_value));
        }

        return array_values($rows);
    }

    /**
     * Per-team revenue.
     *
     * @return array<int, array<string, string|int>>
     */
    public function byTeam(): array
    {
        $rows = [];

        foreach (Team::query()->get() as $team) {
            $total = 0;

            foreach (SalesOrder::query()->where('team_id', $team->id)->get() as $order) {
                $total += Money::toCents($order->verifiedPaymentTotal());
            }

            $rows[] = [
                'team_id' => $team->id,
                'team_code' => $team->team_code,
                'collected' => Money::format($total),
            ];
        }

        return $rows;
    }

    /**
     * Catalog-driven per-product analytics.
     *
     * Every catalog product gets a row (zero-filled), plus an
     * "Uncataloged" bucket for legacy orders that only carry a product
     * name. A product an admin creates therefore appears in all
     * reports immediately.
     *
     * Per row:
     *   product               catalog product name (or "Uncataloged")
     *   orders                orders created from that product
     *   collected_revenue     verified payments only (exact cents math)
     *   active_subscriptions  active subscriptions whose order is that product
     *   active_customers      distinct paying customers behind those active subs
     *   pending_collection    closing value not yet covered by verified payments
     *
     * @return array<int, array<string, string|int>>
     */
    public function productReport(): array
    {
        $orders = SalesOrder::all();
        $rows = [];

        foreach (Product::orderBy('name')->get() as $product) {
            $rows[$product->name] = $this->rowFor((string) $product->name, $orders->where('product_id', $product->id));
        }

        $uncataloged = $orders->whereNull('product_id')->filter(fn (SalesOrder $o) => filled($o->product_name));

        if ($uncataloged->isNotEmpty()) {
            $rows['Uncataloged'] = $this->rowFor('Uncataloged', $uncataloged);
        }

        return array_values($rows);
    }

    /**
     * Per-product revenue (by catalog product name on the order).
     * Kept for backwards compatibility; delegates to the catalog-driven
     * report so new products always show up.
     *
     * @return array<int, array<string, string|int>>
     */
    public function byProduct(?Team $team = null): array
    {
        if ($team !== null) {
            $rows = [];

            foreach ($this->ordersFor($team) as $order) {
                $key = (string) ($order->product_name ?? 'unknown');
                $rows[$key] = $rows[$key] ?? ['product' => $key, 'collected' => '0.00', 'orders' => 0];
                $rows[$key]['collected'] = Money::format(Money::toCents($rows[$key]['collected']) + Money::toCents($order->verifiedPaymentTotal()));
                $rows[$key]['orders']++;
            }

            return array_values($rows);
        }

        $report = $this->productReport();

        return array_map(static fn (array $row): array => [
            'product' => $row['product'],
            'orders' => $row['orders'],
            'collected' => $row['collected_revenue'],
        ], $report);
    }

    /**
     * Monthly collected revenue for the last $months months, keyed
     * YYYY-MM.
     *
     * @return array<string, string>
     */
    public function monthlyRevenue(int $months = 12): array
    {
        $series = [];
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i)->format('Y-m');
            $total = 0;

            foreach (SalesOrder::query()->where('order_status', 'active')->get() as $order) {
                // Collected revenue is attributed to the month of the
                // subscription start.
                $sub = $order->subscription;

                if (! $sub || ! $sub->started_at) {
                    continue;
                }

                if ($sub->started_at->format('Y-m') === $month) {
                    $total += Money::toCents($sub->amount_paid);
                }
            }

            $series[$month] = Money::format($total);
        }

        return $series;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, SalesOrder> $productOrders
     * @return array<string, string|int>
     */
    protected function rowFor(string $label, \Illuminate\Database\Eloquent\Collection $productOrders): array
    {
        $collected = 0;
        $closing = 0;

        foreach ($productOrders as $order) {
            $collected += Money::toCents($order->verifiedPaymentTotal());
            $closing += Money::toCents($order->closing_value);
        }

        $orderIds = $productOrders->map->id->values();

        $activeSubscriptions = Subscription::where('status', 'active')
            ->whereNotNull('order_id')
            ->whereIn('order_id', $orderIds)
            ->get();

        $activeCustomers = $activeSubscriptions
            ->map->customer_id
            ->filter()
            ->unique()
            ->count();

        return [
            'product' => $label,
            'orders' => $productOrders->count(),
            'collected_revenue' => Money::format($collected),
            'active_subscriptions' => $activeSubscriptions->count(),
            'active_customers' => $activeCustomers,
            'pending_collection' => Money::format(max($closing - $collected, 0)),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SalesOrder>
     */
    protected function ordersFor(?Team $team, ?User $employee = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = SalesOrder::query();

        if ($team) {
            $query->where('team_id', $team->id);
        }

        if ($employee) {
            $query->where('assigned_employee_id', $employee->id);
        }

        return $query->with('subscription')->get();
    }
}
