<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard with overview stats.
     */
    public function index(Request $request): View|JsonResponse
    {
        $stats = [
            'users_total' => User::count(),
            'admins_total' => Admin::count(),
            'users_last_7_days' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'users_last_30_days' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'orders_total' => SalesOrder::count(),
            'orders_active' => SalesOrder::where('order_status', 'active')->count(),
            'products_total' => \App\Models\Product::count(),
            'products_active' => \App\Models\Product::where('status', 'active')->count(),
        ];

        $productReport = app(\App\Services\SalesAnalyticsService::class)->productReport();

        $recentUsers = User::latest()->take(5)->get(['id', 'name', 'username', 'email', 'created_at']);

        $recentAdmins = Admin::latest()->take(5)->get(['id', 'name', 'username', 'email', 'created_at']);

        if ($request->wantsJson()) {
            return response()->json([
                'stats' => $stats,
                'product_report' => $productReport,
                'recent_users' => $recentUsers,
                'recent_admins' => $recentAdmins,
                'admin' => $request->user('admin')->only(['id', 'name', 'username', 'email']),
            ]);
        }

        return view('admin.dashboard', compact('stats', 'productReport', 'recentUsers', 'recentAdmins'));
    }
}
