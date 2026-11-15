<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\SalesOrder;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): mixed
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $viewMode = $request->query('view', 'my_data');
        $hasTeamAccess = $employee->hasActiveTeamAccess();
        $activeGrant = null;

        if ($viewMode === 'team_data') {
            if (! $hasTeamAccess || ! $employee->team_id) {
                return redirect()->route('employee.dashboard', ['view' => 'my_data'])
                    ->with('warning', 'You do not currently have an active Team Data access grant from Super Admin.');
            }

            $today = now()->startOfDay()->toDateString();
            $activeGrant = EmployeeTeamAccessGrant::where('user_id', $employee->id)
                ->whereNull('revoked_at')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->first();

            $ordersQuery = SalesOrder::where('sales_orders.team_id', $employee->team_id)
                ->whereDate('sales_orders.created_at', '>=', $activeGrant->start_date)
                ->whereDate('sales_orders.created_at', '<=', $activeGrant->end_date);

            $subscriptionsQuery = Subscription::whereHas('order', function ($q) use ($employee, $activeGrant) {
                $q->where('sales_orders.team_id', $employee->team_id)
                    ->whereDate('sales_orders.created_at', '>=', $activeGrant->start_date)
                    ->whereDate('sales_orders.created_at', '<=', $activeGrant->end_date);
            });
        } else {
            $viewMode = 'my_data';
            $ordersQuery = SalesOrder::where('sales_orders.assigned_employee_id', $employee->id);

            $subscriptionsQuery = Subscription::whereHas('order', function ($q) use ($employee) {
                $q->where('sales_orders.assigned_employee_id', $employee->id);
            });
        }

        $totalOrders = (clone $ordersQuery)->count();
        $closingValue = (clone $ordersQuery)->sum('closing_value');

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

        $pendingAmount = (clone $ordersQuery)
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->sum('closing_value');

        $activeSubscriptions = (clone $subscriptionsQuery)
            ->where('status', 'active')->count();

        $expiringSubscriptions = (clone $subscriptionsQuery)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->count();

        // 9 standard metrics for Phase 5 cards:
        $recentOrders = (clone $ordersQuery)->with('product')->orderByDesc('id')->limit(5)->get();
        $conversionRate = $totalOrders > 0 ? 100 : 0;
        $avgOrderValue = $totalOrders > 0 ? (float) $closingValue / $totalOrders : 0;

        return view('employee.dashboard', [
            'employee' => $employee,
            'viewMode' => $viewMode,
            'hasTeamAccess' => $hasTeamAccess,
            'activeGrant' => $activeGrant,
            'totalOrders' => $totalOrders,
            'closingValue' => number_format((float) $closingValue, 2),
            'verifiedRevenue' => number_format((float) $verifiedRevenue, 2),
            'pendingAmount' => number_format((float) $pendingAmount, 2),
            'activeSubscriptions' => $activeSubscriptions,
            'expiringSubscriptions' => $expiringSubscriptions,
            'recentOrders' => $recentOrders,
            'conversionRate' => $conversionRate,
            'avgOrderValue' => number_format($avgOrderValue, 2),
        ]);
    }

    public function reports(): View
    {
        return view('employee.reports', ['employee' => auth()->user()]);
    }

    public function settings(): View
    {
        return view('employee.settings', ['employee' => auth()->user()]);
    }

    public function help(): View
    {
        return view('employee.help', ['employee' => auth()->user()]);
    }
}
