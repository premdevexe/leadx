<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected SalesAnalyticsService $analytics)
    {
    }

    /**
     * Show the admin dashboard with overview stats and dynamic sales analytics.
     */
    public function index(Request $request): View|JsonResponse
    {
        $overview = $this->analytics->overview();
        $productReport = $this->analytics->productReport();
        $teamReport = $this->analytics->teamReport();
        $monthlyReport = $this->analytics->monthlyReport(6);

        $stats = [
            'users_total' => User::count(),
            'admins_total' => Admin::count(),
            'teams_total' => Team::count(),
            'orders_total' => $overview['total_orders'],
            'orders_active' => $overview['active_subscriptions'],
            'verified_revenue' => $overview['verified_revenue'],
            'paying_customers' => $overview['total_paying_customers'],
            'active_subscriptions' => $overview['active_subscriptions'],
            'expired_subscriptions' => $overview['expired_subscriptions'],
            'pending_collections' => $overview['pending_collections'],
            'total_closing_value' => $overview['total_closing_value'],
            'total_discounts' => $overview['total_discounts'],
            'total_product_value' => $overview['total_product_value'],
            'users_last_7_days' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'users_last_30_days' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'products_total' => Product::count(),
            'products_active' => Product::where('status', 'active')->count(),
        ];

        $recentOrders = SalesOrder::with('team')->latest()->take(5)->get();
        $recentUsers = User::latest()->take(5)->get(['id', 'name', 'username', 'email', 'created_at']);
        $recentAdmins = Admin::latest()->take(5)->get(['id', 'name', 'username', 'email', 'created_at']);

        if ($request->wantsJson()) {
            return response()->json([
                'stats' => $stats,
                'overview' => $overview,
                'product_report' => $productReport,
                'team_report' => $teamReport,
                'monthly_report' => $monthlyReport,
                'recent_orders' => $recentOrders,
                'recent_users' => $recentUsers,
                'recent_admins' => $recentAdmins,
                'admin' => $request->user('admin')->only(['id', 'name', 'username', 'email']),
            ]);
        }

        return view('admin.dashboard', compact(
            'stats',
            'overview',
            'productReport',
            'teamReport',
            'monthlyReport',
            'recentOrders',
            'recentUsers',
            'recentAdmins'
        ));
    }
}
