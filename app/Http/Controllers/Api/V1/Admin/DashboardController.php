<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PushNotificationLog;
use App\Models\Refund;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin dashboard — JSON mirror of the web /admin/dashboard.
 *
 * Returns the same stat block the web page computes (users, orders,
 * revenue, subscriptions, refunds, notification volumes) plus the
 * analytics reports, so a Flutter admin app renders the dashboard without
 * a web page.
 */
class DashboardController extends Controller
{
    public function __construct(protected SalesAnalyticsService $analytics)
    {
    }

    /**
     * GET /api/v1/admin/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $overview = $this->analytics->overview();
        $productReport = $this->analytics->productReport();
        $teamReport = $this->analytics->teamReport();
        $monthlyReport = $this->analytics->monthlyReport(6);

        // Money fields come back from the analytics service as formatted
        // strings ("$1,234.56") — pass them through verbatim so the API
        // matches the web panel's display values exactly.
        $stats = [
            'users_total' => User::count(),
            'employees_total' => User::where('role', 'employee')->count(),
            'employees_active' => User::where('role', 'employee')->where('status', 'active')->count(),
            'admins_total' => Admin::count(),
            'teams_total' => Team::count(),
            'orders_total' => $overview['total_orders'],
            'orders_active' => $overview['active_subscriptions'],
            'orders_pending' => SalesOrder::where('order_status', 'pending')->count(),
            'closing_approval_requests' => SalesOrder::where('closing_value_status', 'Requested')->count(),
            'verified_revenue' => $overview['verified_revenue'],
            'paying_customers' => $overview['total_paying_customers'],
            'active_subscriptions' => $overview['active_subscriptions'],
            'expired_subscriptions' => $overview['expired_subscriptions'],
            'expiring_subscriptions' => Subscription::where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30))
                ->where('expires_at', '>', now())
                ->count(),
            'pending_collections' => $overview['pending_collections'],
            'pending_payments_count' => Payment::where('payment_status', 'pending')->count(),
            'total_closing_value' => $overview['total_closing_value'],
            'total_discounts' => $overview['total_discounts'],
            'total_product_value' => $overview['total_product_value'],
            'refunds_count' => Refund::count(),
            'refunds_total' => (float) Refund::sum('amount'),
            'products_total' => Product::count(),
            'products_active' => Product::where('status', 'active')->count(),
        ];

        $notificationSummary = [
            'push_total' => PushNotificationLog::count(),
            'push_delivered' => PushNotificationLog::where('status', 'delivered')->count(),
            'email_total' => EmailLog::count(),
            'email_sent' => EmailLog::where('status', 'sent')->count(),
            'email_failed' => EmailLog::where('status', 'failed')->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'overview' => $overview,
            'product_report' => $productReport,
            'team_report' => $teamReport,
            'monthly_report' => $monthlyReport,
            'recent_orders' => SalesOrder::with(['team', 'product', 'assignedEmployee'])
                ->latest()->take(5)->get(),
            'recent_activities' => AuditLog::with('actor')->latest()->take(6)->get(),
            'notification_summary' => $notificationSummary,
        ]);
    }
}
