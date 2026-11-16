<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Employee dashboard — JSON mirror of the web panel's widget set
 * (GET /employee/dashboard in the employee portal).
 *
 * The same my_data / team_data scoping as the web dashboard, but returned as
 * a single JSON document so the Flutter app can render every card without a
 * web page. Card/summary values are computed with the same queries the web
 * controller uses; permission gating for individual cards is available in
 * the Flutter UI via the `permissions` block, not by hiding data.
 */
class DashboardController extends Controller
{
    use EmployeeScoped;

    /**
     * GET /api/v1/employee/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $requestedView = $request->input('view', 'my_data');
        $resolved = $this->resolveView($employee, $requestedView);
        $view = $resolved['view'];
        $grant = $resolved['grant'];

        $ordersQuery = $this->scopedOrders($employee, $view, $grant);
        $subscriptionsQuery = $this->scopedSubscriptions($employee, $view, $grant);
        $followUpsQuery = $this->scopedFollowUps($employee, $view, $grant);

        // 1. Total orders
        $totalOrders = (clone $ordersQuery)->count();

        // 2. Sales / closing value
        $closingValue = (clone $ordersQuery)->sum('closing_value');

        // 3. Verified revenue (same stale-payment window as the web panel)
        $staleAfterDays = (int) config('leadx.payments.stale_after_days', 90);
        $cutoff = now()->subDays($staleAfterDays);
        $verifiedRevenue = (clone $ordersQuery)
            ->join('payments', 'sales_orders.id', '=', 'payments.order_id')
            ->where(function ($q) {
                $q->whereNotNull('payments.verified_by_admin_id')
                    ->orWhere('payments.verification_source', 'gateway');
            })
            ->whereNotIn('payments.payment_status', ['failed', 'refunded'])
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('payments.verified_at')
                    ->orWhere('payments.verified_at', '>=', $cutoff);
            })
            ->sum('payments.amount');

        // 4. Distinct customers (prefixed: the revenue stat joins payments,
        // which also carries a customer_phone-like set of columns)
        $customersCount = (clone $ordersQuery)
            ->whereNotNull('sales_orders.customer_phone')
            ->where('sales_orders.customer_phone', '!=', '')
            ->distinct('sales_orders.customer_phone')
            ->count('sales_orders.customer_phone');
        if ($customersCount === 0) {
            $customersCount = (clone $ordersQuery)->distinct('sales_orders.customer_name')->count('sales_orders.customer_name');
        }

        // 5. Active subscriptions
        $activeSubscriptions = (clone $subscriptionsQuery)->where('status', 'active')->count();

        // 6. Pending payments
        $pendingPaymentsCount = (clone $ordersQuery)->whereIn('payment_status', ['pending', 'partially_paid'])->count();
        $pendingAmount = (clone $ordersQuery)->whereIn('payment_status', ['pending', 'partially_paid'])->sum('closing_value');

        // 7. Expiring subscriptions (active, expiring within 30 days, not yet expired)
        $expiringSubscriptions = (clone $subscriptionsQuery)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->count();

        // 8. Follow-ups (open)
        $followUpsCount = (clone $followUpsQuery)->whereIn('status', ['scheduled', 'rescheduled'])->count();

        // 9. Monthly performance
        $monthlyOrdersCount = (clone $ordersQuery)
            ->whereYear('sales_orders.created_at', now()->year)
            ->whereMonth('sales_orders.created_at', now()->month)
            ->count();
        $monthlyClosingValue = (clone $ordersQuery)
            ->whereYear('sales_orders.created_at', now()->year)
            ->whereMonth('sales_orders.created_at', now()->month)
            ->sum('closing_value');

        $recentOrders = (clone $ordersQuery)->with('product')->orderByDesc('id')->limit(5)->get();

        $recentFollowUps = (clone $followUpsQuery)->with('customer')->orderBy('scheduled_at')->limit(4)->get();

        $expiringSubscriptionsList = (clone $subscriptionsQuery)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->limit(4)
            ->get();

        $recentNotifications = $employee->notifications()->latest()->limit(4)->get();

        // 6-month sales trend
        $monthlySalesChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $monthlySalesChart[] = [
                'month' => $monthDate->format('M Y'),
                'orders' => (clone $ordersQuery)
                    ->whereYear('sales_orders.created_at', $monthDate->year)
                    ->whereMonth('sales_orders.created_at', $monthDate->month)
                    ->count(),
                'closing_value' => (float) ((clone $ordersQuery)
                    ->whereYear('sales_orders.created_at', $monthDate->year)
                    ->whereMonth('sales_orders.created_at', $monthDate->month)
                    ->sum('closing_value')),
            ];
        }

        return response()->json([
            'meta' => [
                'currency_symbol' => Setting::currencySymbol(),
                'view' => $view,
                'warning' => $resolved['warning'],
            ],
            'summary' => [
                'total_orders' => $totalOrders,
                'closing_value' => (float) $closingValue,
                'verified_revenue' => (float) $verifiedRevenue,
                'customers_count' => $customersCount,
                'active_subscriptions' => $activeSubscriptions,
                'pending_payments_count' => $pendingPaymentsCount,
                'pending_amount' => (float) $pendingAmount,
                'expiring_subscriptions' => $expiringSubscriptions,
                'follow_ups' => $followUpsCount,
                'monthly_orders' => $monthlyOrdersCount,
                'monthly_closing_value' => (float) $monthlyClosingValue,
            ],
            'recent_orders' => ['data' => OrderResource::collection($recentOrders)],
            'recent_follow_ups' => [
                'data' => $recentFollowUps->map(fn ($fu) => [
                    'id' => $fu->id,
                    'title' => $fu->title,
                    'status' => $fu->status,
                    'scheduled_at' => $fu->scheduled_at?->toIso8601String(),
                    'priority' => $fu->priority,
                    'customer' => $fu->customer ? ['id' => $fu->customer->id, 'name' => $fu->customer->name] : null,
                ])->all(),
            ],
            'expiring_subscriptions' => ['data' => SubscriptionResource::collection($expiringSubscriptionsList)],
            'recent_notifications' => $recentNotifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all(),
            'monthly_sales_chart' => $monthlySalesChart,
        ]);
    }
}
