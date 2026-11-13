<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Enterprise Sales Analytics and Reporting Service.
 *
 * All figures are authoritative DECIMAL(15,2) strings produced through
 * exact integer cent arithmetic ({@see Money}).
 * Pending, failed and refunded payments never count toward collected revenue.
 */
class SalesAnalyticsService
{
    public const CACHE_PREFIX = 'leadx_analytics_';

    /**
     * Clear cached analytics reports when transactions change.
     */
    public static function invalidateCache(): void
    {
        Cache::forget(self::CACHE_PREFIX.'overview_all');
        Cache::forget(self::CACHE_PREFIX.'products_all');
        Cache::forget(self::CACHE_PREFIX.'monthly_all');
    }

    /**
     * Apply standard analytics filters to a SalesOrder query builder.
     *
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters = [], string $dateColumn = 'created_at'): Builder
    {
        $range = $filters['range'] ?? null;

        if ($range === 'today') {
            $query->whereDate($dateColumn, Carbon::today());
        } elseif ($range === 'last_7_days') {
            $query->where($dateColumn, '>=', Carbon::now()->subDays(7)->startOfDay());
        } elseif ($range === 'last_30_days') {
            $query->where($dateColumn, '>=', Carbon::now()->subDays(30)->startOfDay());
        } elseif ($range === 'this_month') {
            $query->where($dateColumn, '>=', Carbon::now()->startOfMonth());
        } elseif ($range === 'previous_month') {
            $query->whereBetween($dateColumn, [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ]);
        } elseif ($range === 'custom' || (! empty($filters['start_date']) || ! empty($filters['from_date']))) {
            $start = $filters['start_date'] ?? $filters['from_date'] ?? null;
            $end = $filters['end_date'] ?? $filters['to_date'] ?? null;

            if ($start && $end) {
                $query->whereBetween($dateColumn, [
                    Carbon::parse($start)->startOfDay(),
                    Carbon::parse($end)->endOfDay(),
                ]);
            } elseif ($start) {
                $query->where($dateColumn, '>=', Carbon::parse($start)->startOfDay());
            } elseif ($end) {
                $query->where($dateColumn, '<=', Carbon::parse($end)->endOfDay());
            }
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['employee_id'])) {
            $query->where('assigned_employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', (int) $filters['team_id']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['order_status'])) {
            $query->where('order_status', $filters['order_status']);
        }

        return $query;
    }

    /**
     * Top-level KPI overview across all orders, products, and revenue.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters = []): array
    {
        $cacheKey = empty($filters) ? self::CACHE_PREFIX.'overview_all' : null;

        if ($cacheKey && Cache::has($cacheKey)) {
            return (array) Cache::get($cacheKey);
        }

        $orderQuery = SalesOrder::query()->with(['payments', 'subscription']);
        $this->applyFilters($orderQuery, $filters);
        $orders = $orderQuery->get();

        $totalProductCents = 0;
        $totalClosingCents = 0;
        $collectedRevenueCents = 0;
        $refundsCents = 0;

        $orderIds = [];

        foreach ($orders as $order) {
            $orderIds[] = $order->id;
            $snapshot = (float) ($order->product_price_snapshot ?? 0);
            $productVal = $snapshot > 0 ? $snapshot : (float) ($order->product_value ?? 0);
            $totalProductCents += Money::toCents($productVal);
            $totalClosingCents += Money::toCents($order->closing_value);
            $collectedRevenueCents += Money::toCents($order->verifiedPaymentTotal());

            foreach ($order->payments as $payment) {
                if ($payment->payment_status === 'refunded') {
                    $refundsCents += Money::toCents($payment->amount);
                }
            }
        }

        $pendingCollectionCents = max(0, $totalClosingCents - $collectedRevenueCents);
        $totalDiscountsCents = max(0, $totalProductCents - $totalClosingCents);

        // Subscriptions
        $subQuery = Subscription::query()->whereNotNull('order_id')->whereIn('order_id', empty($orderIds) ? [0] : $orderIds);
        $activeSubsCount = (clone $subQuery)->where('status', 'active')->count();
        $expiredSubsCount = (clone $subQuery)->where('status', 'expired')->count();

        // Paying CRM customers
        $payingCustomersCount = Customer::where('status', 'paying')
            ->when(! empty($filters['team_id']), fn ($q) => $q->where('team_id', (int) $filters['team_id']))
            ->count();

        $result = [
            'verified_revenue' => Money::format($collectedRevenueCents),
            'total_orders' => $orders->count(),
            'total_paying_customers' => $payingCustomersCount,
            'active_subscriptions' => $activeSubsCount,
            'expired_subscriptions' => $expiredSubsCount,
            'pending_collections' => Money::format($pendingCollectionCents),
            'total_closing_value' => Money::format($totalClosingCents),
            'total_discounts' => Money::format($totalDiscountsCents),
            'total_product_value' => Money::format($totalProductCents),
            'total_refunds' => Money::format($refundsCents),
            'monthly_revenue' => $this->monthlyRevenue(6, $filters),
            'filters' => $filters,
        ];

        if ($cacheKey) {
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Financial report distinguishing Product Value, Closing Value,
     * Collected Payments, Outstanding Balance, Discounts, and Refunds.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function revenueBreakdown(array $filters = []): array
    {
        $orderQuery = SalesOrder::query()->with(['payments']);
        $this->applyFilters($orderQuery, $filters);
        $orders = $orderQuery->get();

        $productValueCents = 0;
        $closingValueCents = 0;
        $amountReceivedCents = 0;
        $refundsCents = 0;
        $byMethodCents = [];

        foreach ($orders as $order) {
            $snapshot = (float) ($order->product_price_snapshot ?? 0);
            $productVal = $snapshot > 0 ? $snapshot : (float) ($order->product_value ?? 0);
            $productValueCents += Money::toCents($productVal);
            $closingValueCents += Money::toCents($order->closing_value);
            $amountReceivedCents += Money::toCents($order->verifiedPaymentTotal());

            foreach ($order->payments as $payment) {
                if ($payment->payment_status === 'refunded') {
                    $refundsCents += Money::toCents($payment->amount);
                    continue;
                }

                $isVerified = $payment->verified_by_admin_id !== null || $payment->verification_source === 'gateway';
                if ($isVerified && ! in_array($payment->payment_status, ['failed', 'refunded'], true)) {
                    $method = $payment->payment_method ?: 'other';
                    $byMethodCents[$method] = ($byMethodCents[$method] ?? 0) + Money::toCents($payment->amount);
                }
            }
        }

        $pendingAmountCents = max(0, $closingValueCents - $amountReceivedCents);
        $discountsCents = max(0, $productValueCents - $closingValueCents);

        $byMethodFormatted = [];
        foreach ($byMethodCents as $method => $cents) {
            $byMethodFormatted[$method] = Money::format($cents);
        }

        return [
            'product_value' => Money::format($productValueCents),
            'closing_value' => Money::format($closingValueCents),
            'amount_received' => Money::format($amountReceivedCents),
            'pending_amount' => Money::format($pendingAmountCents),
            'discounts' => Money::format($discountsCents),
            'refunds' => Money::format($refundsCents),
            'by_payment_method' => $byMethodFormatted,
        ];
    }

    /**
     * Catalog-driven per-product analytics with optional filtering.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function productReport(array $filters = []): array
    {
        $cacheKey = empty($filters) ? self::CACHE_PREFIX.'products_all' : null;
        if ($cacheKey && Cache::has($cacheKey)) {
            return (array) Cache::get($cacheKey);
        }

        $orderQuery = SalesOrder::query()->with(['payments', 'subscription']);
        $this->applyFilters($orderQuery, $filters);
        $orders = $orderQuery->get();

        $rows = [];
        $products = Product::orderBy('name')->get();

        foreach ($products as $product) {
            $productOrders = $orders->where('product_id', $product->id);
            $rows[$product->name] = $this->rowFor((string) $product->name, $productOrders, $product->id);
        }

        $uncataloged = $orders->whereNull('product_id')->filter(fn (SalesOrder $o) => filled($o->product_name));
        if ($uncataloged->isNotEmpty()) {
            $rows['Uncataloged'] = $this->rowFor('Uncataloged', $uncataloged, null);
        }

        $result = array_values($rows);

        if ($cacheKey) {
            Cache::put($cacheKey, $result, 60);
        }

        return $result;
    }

    /**
     * Employee performance analytics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function employeeReport(array $filters = []): array
    {
        $orderQuery = SalesOrder::query()->with(['payments', 'team', 'assignedEmployee']);
        $this->applyFilters($orderQuery, $filters);
        $orders = $orderQuery->get();

        $employeeQuery = User::where('role', 'employee')->with('team')->orderBy('username');
        if (! empty($filters['team_id'])) {
            $employeeQuery->where('team_id', (int) $filters['team_id']);
        }
        if (! empty($filters['employee_id'])) {
            $employeeQuery->where('id', (int) $filters['employee_id']);
        }
        $employees = $employeeQuery->get();

        $rows = [];

        foreach ($employees as $emp) {
            $empOrders = $orders->where('assigned_employee_id', $emp->id);

            $collectedCents = 0;
            $closingCents = 0;
            foreach ($empOrders as $order) {
                $collectedCents += Money::toCents($order->verifiedPaymentTotal());
                $closingCents += Money::toCents($order->closing_value);
            }

            $orderIds = $empOrders->pluck('id')->all();
            $activeSubs = empty($orderIds) ? 0 : Subscription::where('status', 'active')->whereIn('order_id', $orderIds)->count();

            $rows[] = [
                'employee_id' => $emp->id,
                'employee_name' => $emp->name ?: $emp->username,
                'username' => $emp->username,
                'team_id' => $emp->team_id,
                'team_code' => $emp->team?->team_code ?? '—',
                'total_orders' => $empOrders->count(),
                'verified_revenue' => Money::format($collectedCents),
                'closing_value' => Money::format($closingCents),
                'collections' => Money::format($collectedCents),
                'pending_collections' => Money::format(max(0, $closingCents - $collectedCents)),
                'active_subscriptions' => $activeSubs,
            ];
        }

        usort($rows, fn ($a, $b) => Money::toCents($b['verified_revenue']) <=> Money::toCents($a['verified_revenue']));

        return $rows;
    }

    /**
     * Team performance analytics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function teamReport(array $filters = []): array
    {
        $orderQuery = SalesOrder::query()->with(['payments', 'team', 'assignedEmployee']);
        $this->applyFilters($orderQuery, $filters);
        $orders = $orderQuery->get();

        $teamQuery = Team::query()->orderBy('team_code');
        if (! empty($filters['team_id'])) {
            $teamQuery->where('id', (int) $filters['team_id']);
        }
        $teams = $teamQuery->get();

        $rows = [];

        foreach ($teams as $team) {
            $teamOrders = $orders->where('team_id', $team->id);

            $collectedCents = 0;
            $closingCents = 0;
            foreach ($teamOrders as $order) {
                $collectedCents += Money::toCents($order->verifiedPaymentTotal());
                $closingCents += Money::toCents($order->closing_value);
            }

            $orderIds = $teamOrders->pluck('id')->all();
            $activeSubs = empty($orderIds) ? 0 : Subscription::where('status', 'active')->whereIn('order_id', $orderIds)->count();

            $members = User::where('role', 'employee')->where('team_id', $team->id)->get();
            $membersPerformance = [];

            foreach ($members as $member) {
                $memOrders = $teamOrders->where('assigned_employee_id', $member->id);
                $memCollected = 0;
                $memClosing = 0;
                foreach ($memOrders as $mo) {
                    $memCollected += Money::toCents($mo->verifiedPaymentTotal());
                    $memClosing += Money::toCents($mo->closing_value);
                }

                $membersPerformance[] = [
                    'employee_id' => $member->id,
                    'username' => $member->username,
                    'name' => $member->name ?: $member->username,
                    'orders' => $memOrders->count(),
                    'revenue' => Money::format($memCollected),
                    'closing_value' => Money::format($memClosing),
                ];
            }

            $rows[] = [
                'team_id' => $team->id,
                'team_code' => $team->team_code,
                'team_name' => $team->team_name,
                'orders' => $teamOrders->count(),
                'revenue' => Money::format($collectedCents),
                'collections' => Money::format($collectedCents),
                'closing_value' => Money::format($closingCents),
                'pending_collections' => Money::format(max(0, $closingCents - $collectedCents)),
                'active_subscriptions' => $activeSubs,
                'members_count' => $members->count(),
                'member_performance' => $membersPerformance,
            ];
        }

        usort($rows, fn ($a, $b) => Money::toCents($b['revenue']) <=> Money::toCents($a['revenue']));

        return $rows;
    }

    /**
     * Monthly revenue series.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function monthlyRevenue(int $months = 12, array $filters = []): array
    {
        $series = [];
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $orderQuery = SalesOrder::query()->with(['payments', 'subscription']);
        $this->applyFilters($orderQuery, $filters);
        $orders = $orderQuery->get();

        for ($i = 0; $i < $months; $i++) {
            $currentMonth = $start->copy()->addMonths($i);
            $monthKey = $currentMonth->format('Y-m');
            $totalCents = 0;

            foreach ($orders as $order) {
                $sub = $order->subscription;
                if ($sub && $sub->started_at && $sub->started_at->format('Y-m') === $monthKey) {
                    $totalCents += Money::toCents($order->verifiedPaymentTotal());
                } elseif (! $sub) {
                    foreach ($order->payments as $payment) {
                        $isVerified = $payment->verified_by_admin_id !== null || $payment->verification_source === 'gateway';
                        if ($isVerified && ! in_array($payment->payment_status, ['failed', 'refunded'], true)) {
                            $pDate = $payment->verified_at ?: $payment->payment_date ?: $payment->created_at;
                            if ($pDate && Carbon::parse($pDate)->format('Y-m') === $monthKey) {
                                $totalCents += Money::toCents($payment->amount);
                            }
                        }
                    }
                }
            }

            $series[$monthKey] = Money::format($totalCents);
        }

        return $series;
    }

    /**
     * Monthly report with order count and subscription count.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function monthlyReport(int $months = 12, array $filters = []): array
    {
        $rows = [];
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $orderQuery = SalesOrder::query()->with(['payments', 'subscription']);
        $this->applyFilters($orderQuery, $filters);
        $orders = $orderQuery->get();

        for ($i = 0; $i < $months; $i++) {
            $currentMonth = $start->copy()->addMonths($i);
            $monthKey = $currentMonth->format('Y-m');
            $label = $currentMonth->format('M Y');

            $monthOrders = $orders->filter(function ($o) use ($monthKey) {
                return $o->created_at && $o->created_at->format('Y-m') === $monthKey;
            });

            $revenueCents = 0;
            $subsCount = 0;

            foreach ($monthOrders as $order) {
                $revenueCents += Money::toCents($order->verifiedPaymentTotal());
                if ($order->subscription && $order->subscription->status === 'active') {
                    $subsCount++;
                }
            }

            $rows[] = [
                'month' => $monthKey,
                'label' => $label,
                'revenue' => Money::format($revenueCents),
                'orders' => $monthOrders->count(),
                'subscriptions' => $subsCount,
            ];
        }

        return $rows;
    }

    /**
     * Team self-analytics (strictly scoped to this team).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function teamAnalytics(Team $team, array $filters = []): array
    {
        $filters['team_id'] = $team->id;

        $overview = $this->overview($filters);
        $employees = $this->employeeReport($filters);
        $products = $this->productReport($filters);
        $monthly = $this->monthlyReport(6, $filters);

        return [
            'team' => [
                'id' => $team->id,
                'team_code' => $team->team_code,
                'team_name' => $team->team_name,
            ],
            'overview' => $overview,
            'members' => $employees,
            'products' => $products,
            'monthly' => $monthly,
        ];
    }

    /**
     * Employee self-analytics (strictly scoped to this individual employee).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function employeeAnalytics(User $employee, array $filters = []): array
    {
        $filters['employee_id'] = $employee->id;

        $overview = $this->overview($filters);
        $products = $this->productReport($filters);
        $monthly = $this->monthlyReport(6, $filters);

        $recentOrders = SalesOrder::where('assigned_employee_id', $employee->id)
            ->with(['product', 'subscription'])
            ->latest()
            ->take(5)
            ->get();

        return [
            'employee' => [
                'id' => $employee->id,
                'username' => $employee->username,
                'name' => $employee->name ?: $employee->username,
                'team_id' => $employee->team_id,
            ],
            'overview' => $overview,
            'products' => $products,
            'monthly' => $monthly,
            'recent_orders' => $recentOrders,
        ];
    }

    /**
     * Detailed paginated sales orders report.
     *
     * @param  array<string, mixed>  $filters
     */
    public function ordersReport(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = SalesOrder::query()->with(['team', 'assignedEmployee', 'payments', 'subscription', 'product']);
        $this->applyFilters($query, $filters);

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Detailed paginated payments report.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paymentsReport(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Payment::query()->with(['order.team', 'verifiedByAdmin']);

        $range = $filters['range'] ?? null;
        if ($range === 'today') {
            $query->whereDate('payment_date', Carbon::today());
        } elseif ($range === 'last_7_days') {
            $query->where('payment_date', '>=', Carbon::now()->subDays(7)->startOfDay());
        } elseif ($range === 'last_30_days') {
            $query->where('payment_date', '>=', Carbon::now()->subDays(30)->startOfDay());
        } elseif ($range === 'this_month') {
            $query->where('payment_date', '>=', Carbon::now()->startOfMonth());
        } elseif ($range === 'previous_month') {
            $query->whereBetween('payment_date', [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ]);
        } elseif ($range === 'custom' || (! empty($filters['start_date']) || ! empty($filters['from_date']))) {
            $start = $filters['start_date'] ?? $filters['from_date'] ?? null;
            $end = $filters['end_date'] ?? $filters['to_date'] ?? null;
            if ($start && $end) {
                $query->whereBetween('payment_date', [
                    Carbon::parse($start)->startOfDay(),
                    Carbon::parse($end)->endOfDay(),
                ]);
            }
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['team_id'])) {
            $query->whereHas('order', fn ($q) => $q->where('team_id', (int) $filters['team_id']));
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Detailed paginated subscriptions report.
     *
     * @param  array<string, mixed>  $filters
     */
    public function subscriptionsReport(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Subscription::query()->with(['order.team', 'order.product', 'customer']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', (int) $filters['team_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        $range = $filters['range'] ?? null;
        if ($range === 'today') {
            $query->whereDate('started_at', Carbon::today());
        } elseif ($range === 'this_month') {
            $query->where('started_at', '>=', Carbon::now()->startOfMonth());
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Legacy helper summary for backwards compatibility.
     *
     * @return array<string, string>
     */
    public function summary(?Team $team = null, ?User $employee = null): array
    {
        $filters = [];
        if ($team) {
            $filters['team_id'] = $team->id;
        }
        if ($employee) {
            $filters['employee_id'] = $employee->id;
        }

        $ov = $this->overview($filters);

        return [
            'total_product_value' => $ov['total_product_value'],
            'total_deal_closing_value' => $ov['total_closing_value'],
            'amount_received' => $ov['verified_revenue'],
            'pending_collection' => $ov['pending_collections'],
        ];
    }

    /**
     * Legacy helper byEmployee for backwards compatibility.
     *
     * @return array<int, array<string, string|int>>
     */
    public function byEmployee(?Team $team = null): array
    {
        $filters = [];
        if ($team) {
            $filters['team_id'] = $team->id;
        }

        $report = $this->employeeReport($filters);

        return array_map(fn ($r) => [
            'employee_id' => $r['employee_id'],
            'collected' => $r['verified_revenue'],
            'deal_value' => $r['closing_value'],
        ], $report);
    }

    /**
     * Legacy helper byTeam for backwards compatibility.
     *
     * @return array<int, array<string, string|int>>
     */
    public function byTeam(): array
    {
        $report = $this->teamReport();

        return array_map(fn ($r) => [
            'team_id' => $r['team_id'],
            'team_code' => $r['team_code'],
            'collected' => $r['revenue'],
        ], $report);
    }

    /**
     * Legacy helper byProduct for backwards compatibility.
     *
     * @return array<int, array<string, string|int>>
     */
    public function byProduct(?Team $team = null): array
    {
        $filters = $team ? ['team_id' => $team->id] : [];
        $report = $this->productReport($filters);

        return array_map(fn ($r) => [
            'product' => $r['product'],
            'orders' => $r['orders'],
            'collected' => $r['collected_revenue'],
        ], $report);
    }

    /**
     * Helper to compute row details for a product.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, SalesOrder>  $productOrders
     * @return array<string, mixed>
     */
    protected function rowFor(string $label, \Illuminate\Database\Eloquent\Collection $productOrders, ?int $productId = null): array
    {
        $collectedCents = 0;
        $closingCents = 0;

        foreach ($productOrders as $order) {
            $collectedCents += Money::toCents($order->verifiedPaymentTotal());
            $closingCents += Money::toCents($order->closing_value);
        }

        $orderIds = $productOrders->pluck('id')->all();

        $activeSubscriptions = empty($orderIds) ? collect() : Subscription::where('status', 'active')
            ->whereNotNull('order_id')
            ->whereIn('order_id', $orderIds)
            ->get();

        $activeCustomers = $activeSubscriptions
            ->pluck('customer_id')
            ->filter()
            ->unique()
            ->count();

        // Monthly sales for this product (last 3 months)
        $monthlySales = [];
        for ($i = 2; $i >= 0; $i--) {
            $mKey = Carbon::now()->subMonths($i)->format('Y-m');
            $mOrders = $productOrders->filter(fn ($o) => $o->created_at && $o->created_at->format('Y-m') === $mKey);
            $mRev = 0;
            foreach ($mOrders as $mo) {
                $mRev += Money::toCents($mo->verifiedPaymentTotal());
            }
            $monthlySales[$mKey] = [
                'orders' => $mOrders->count(),
                'revenue' => Money::format($mRev),
            ];
        }

        return [
            'product_id' => $productId,
            'product' => $label,
            'orders' => $productOrders->count(),
            'collected_revenue' => Money::format($collectedCents),
            'active_subscriptions' => $activeSubscriptions->count(),
            'active_customers' => $activeCustomers,
            'pending_collection' => Money::format(max(0, $closingCents - $collectedCents)),
            'monthly_sales' => $monthlySales,
        ];
    }
}
