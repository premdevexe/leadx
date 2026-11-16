<?php

namespace App\Http\Controllers\Admin;

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
use App\Models\UserUiPreference;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const DEFAULT_CARDS = [
        ['key' => 'verified_revenue', 'label' => 'Total Verified Revenue', 'visible' => true, 'order' => 1],
        ['key' => 'pending_collections', 'label' => 'Pending Collections', 'visible' => true, 'order' => 2],
        ['key' => 'total_closing_value', 'label' => 'Total Closing Value', 'visible' => true, 'order' => 3],
        ['key' => 'total_discounts', 'label' => 'Total Discounts', 'visible' => true, 'order' => 4],
        ['key' => 'orders_total', 'label' => 'Total Orders', 'visible' => true, 'order' => 5],
        ['key' => 'paying_customers', 'label' => 'Paying Customers', 'visible' => true, 'order' => 6],
        ['key' => 'active_subscriptions', 'label' => 'Active Subscriptions', 'visible' => true, 'order' => 7],
        ['key' => 'expired_subscriptions', 'label' => 'Expired Subscriptions', 'visible' => true, 'order' => 8],
        ['key' => 'users_total', 'label' => 'Employees', 'visible' => true, 'order' => 9],
        ['key' => 'teams_total', 'label' => 'Teams', 'visible' => true, 'order' => 10],
        ['key' => 'monthly_trend', 'label' => 'Monthly Revenue & Trend', 'visible' => true, 'order' => 11],
        ['key' => 'recent_orders', 'label' => 'Recent Orders', 'visible' => true, 'order' => 12],
        ['key' => 'recent_users', 'label' => 'Recent Employees', 'visible' => true, 'order' => 13],
    ];

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
            'expiring_subscriptions' => Subscription::where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays(30))->where('expires_at', '>', now())->count(),
            'pending_collections' => $overview['pending_collections'],
            'pending_payments_count' => Payment::where('payment_status', 'pending')->count(),
            'total_closing_value' => $overview['total_closing_value'],
            'total_discounts' => $overview['total_discounts'],
            'total_product_value' => $overview['total_product_value'],
            'refunds_count' => Refund::count(),
            'refunds_total' => (float) Refund::sum('amount'),
            'users_last_7_days' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'users_last_30_days' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'products_total' => Product::count(),
            'products_active' => Product::where('status', 'active')->count(),
        ];

        $recentOrders = SalesOrder::with(['team', 'product', 'assignedEmployee'])->latest()->take(5)->get();
        $recentUsers = User::latest()->take(5)->get(['id', 'name', 'username', 'email', 'status', 'created_at']);
        $recentAdmins = Admin::latest()->take(5)->get(['id', 'name', 'username', 'email', 'created_at']);
        $recentActivities = AuditLog::with('actor')->latest()->take(6)->get();
        $notificationSummary = [
            'push_total' => PushNotificationLog::count(),
            'push_delivered' => PushNotificationLog::where('status', 'delivered')->count(),
            'email_total' => EmailLog::count(),
            'email_sent' => EmailLog::where('status', 'sent')->count(),
            'email_failed' => EmailLog::where('status', 'failed')->count(),
        ];

        $adminId = $request->user('admin')?->id ?? 1;
        $cardPreferences = $this->getCardPreferences($adminId);

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
                'recent_activities' => $recentActivities,
                'notification_summary' => $notificationSummary,
                'card_preferences' => $cardPreferences,
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
            'recentAdmins',
            'recentActivities',
            'notificationSummary',
            'cardPreferences'
        ));
    }

    /**
     * Save admin dashboard card layout and visibility preferences.
     */
    public function savePreferences(Request $request): RedirectResponse|JsonResponse
    {
        $adminId = $request->user('admin')?->id ?? 1;

        $validated = $request->validate([
            'cards' => ['required', 'array'],
            'cards.*.key' => ['required', 'string'],
            'cards.*.visible' => ['sometimes'],
            'cards.*.order' => ['nullable', 'integer'],
        ]);

        $sanitized = [];
        foreach ($validated['cards'] as $index => $card) {
            $sanitized[] = [
                'key' => (string) $card['key'],
                'visible' => filter_var($card['visible'] ?? '0', FILTER_VALIDATE_BOOLEAN),
                'order' => isset($card['order']) ? (int) $card['order'] : ($index + 1),
                'label' => $card['label'] ?? null,
            ];
        }

        UserUiPreference::setPreferences('admin', $adminId, 'dashboard_cards', $sanitized);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Dashboard preferences saved successfully.']);
        }

        return back()->with('status', 'Dashboard preferences updated.');
    }

    /**
     * Reset admin dashboard preferences to default.
     */
    public function resetPreferences(Request $request): RedirectResponse|JsonResponse
    {
        $adminId = $request->user('admin')?->id ?? 1;

        UserUiPreference::where('user_type', 'admin')
            ->where('user_id', $adminId)
            ->where('category', 'dashboard_cards')
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Dashboard preferences reset to default.']);
        }

        return back()->with('status', 'Dashboard preferences reset to default.');
    }

    /**
     * Retrieve sorted card preferences merged with defaults.
     */
    protected function getCardPreferences(int $adminId): array
    {
        $saved = UserUiPreference::getPreferences('admin', $adminId, 'dashboard_cards', []);
        if (empty($saved)) {
            return self::DEFAULT_CARDS;
        }

        $savedByKey = collect($saved)->keyBy('key');
        $merged = [];

        foreach (self::DEFAULT_CARDS as $default) {
            if ($savedByKey->has($default['key'])) {
                $item = $savedByKey->get($default['key']);
                $merged[] = [
                    'key' => $default['key'],
                    'label' => $default['label'],
                    'visible' => (bool) ($item['visible'] ?? true),
                    'order' => (int) ($item['order'] ?? $default['order']),
                ];
            } else {
                $merged[] = $default;
            }
        }

        usort($merged, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $merged;
    }
}
