<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeGoogleConnection;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\FollowUp;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\UserUiPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const DEFAULT_CARDS = [
        ['key' => 'orders_count', 'label' => 'My Total Orders', 'visible' => true, 'order' => 1],
        ['key' => 'sales_value', 'label' => 'My Sales / Closing Value', 'visible' => true, 'order' => 2],
        ['key' => 'verified_revenue', 'label' => 'My Verified Revenue', 'visible' => true, 'order' => 3],
        ['key' => 'customers_count', 'label' => 'My Customers', 'visible' => true, 'order' => 4],
        ['key' => 'active_subscriptions', 'label' => 'My Active Subscriptions', 'visible' => true, 'order' => 5],
        ['key' => 'pending_payments', 'label' => 'My Pending Payments', 'visible' => true, 'order' => 6],
        ['key' => 'expiring_subscriptions', 'label' => 'My Expiring Subscriptions', 'visible' => true, 'order' => 7],
        ['key' => 'follow_ups', 'label' => 'My Follow-ups', 'visible' => true, 'order' => 8],
        ['key' => 'monthly_performance', 'label' => 'My Monthly Performance', 'visible' => true, 'order' => 9],
    ];

    public const DEFAULT_SIDEBAR_MODULES = [
        ['key' => 'dashboard', 'route' => 'employee.dashboard', 'label' => 'Dashboard', 'visible' => true, 'order' => 1, 'match' => 'employee.dashboard', 'params' => []],
        ['key' => 'create_order', 'route' => 'employee.orders.create', 'label' => 'Create Order', 'visible' => true, 'order' => 2, 'match' => 'employee.orders.create', 'params' => []],
        ['key' => 'orders', 'route' => 'employee.orders', 'label' => 'My Orders', 'visible' => true, 'order' => 3, 'match' => 'employee.orders*', 'params' => []],
        ['key' => 'customers', 'route' => 'employee.customers', 'label' => 'Customers (CRM)', 'visible' => true, 'order' => 4, 'match' => 'employee.customers*', 'params' => []],
        ['key' => 'payments', 'route' => 'employee.payments', 'label' => 'My Payments', 'visible' => true, 'order' => 5, 'match' => 'employee.payments*', 'params' => []],
        ['key' => 'subscriptions', 'route' => 'employee.subscriptions', 'label' => 'My Subscriptions', 'visible' => true, 'order' => 6, 'match' => 'employee.subscriptions*', 'params' => []],
        ['key' => 'follow_ups', 'route' => 'employee.follow-ups', 'label' => 'My Follow-ups', 'visible' => true, 'order' => 7, 'match' => 'employee.follow-ups*', 'params' => []],
        ['key' => 'expiry_reminders', 'route' => 'employee.subscriptions.expiring', 'label' => 'Expiry Reminders', 'visible' => true, 'order' => 8, 'match' => 'employee.subscriptions.expiring*', 'params' => []],
        ['key' => 'notifications', 'route' => 'employee.notifications', 'label' => 'Notifications', 'visible' => true, 'order' => 9, 'match' => 'employee.notifications*', 'params' => []],
        ['key' => 'calendar', 'route' => 'employee.calendar', 'label' => 'My Google Calendar', 'visible' => true, 'order' => 10, 'match' => 'employee.calendar*', 'params' => []],
        ['key' => 'profile', 'route' => 'employee.profile', 'label' => 'My Profile', 'visible' => true, 'order' => 11, 'match' => 'employee.profile*', 'params' => []],
        ['key' => 'reports', 'route' => 'employee.reports', 'label' => 'My Performance Report', 'visible' => true, 'order' => 12, 'match' => 'employee.reports*', 'params' => []],
    ];

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

            $followUpsQuery = FollowUp::where('team_id', $employee->team_id);
        } else {
            $viewMode = 'my_data';
            $ordersQuery = SalesOrder::where('sales_orders.assigned_employee_id', $employee->id);

            $subscriptionsQuery = Subscription::whereHas('order', function ($q) use ($employee) {
                $q->where('sales_orders.assigned_employee_id', $employee->id);
            });

            $followUpsQuery = FollowUp::where('assigned_employee_id', $employee->id);
        }

        // 1. Total Orders
        $totalOrders = (clone $ordersQuery)->count();

        // 2. Sales / Closing Value
        $closingValue = (clone $ordersQuery)->sum('closing_value');

        // 3. Verified Revenue
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

        // 4. Customers Count
        $customersCount = (clone $ordersQuery)
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->distinct('customer_phone')
            ->count('customer_phone');
        if ($customersCount === 0) {
            $customersCount = (clone $ordersQuery)->distinct('customer_name')->count('customer_name');
        }

        // 5. Active Subscriptions
        $activeSubscriptions = (clone $subscriptionsQuery)
            ->where('status', 'active')
            ->count();

        // 6. Pending Payments
        $pendingPaymentsCount = (clone $ordersQuery)
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->count();
        $pendingAmount = (clone $ordersQuery)
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->sum('closing_value');

        // 7. Expiring Subscriptions
        $expiringSubscriptions = (clone $subscriptionsQuery)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->count();

        // 8. Follow-ups
        $followUpsCount = (clone $followUpsQuery)
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->count();

        // 9. Monthly Performance
        $monthlyOrdersCount = (clone $ordersQuery)
            ->whereYear('sales_orders.created_at', now()->year)
            ->whereMonth('sales_orders.created_at', now()->month)
            ->count();
        $monthlyClosingValue = (clone $ordersQuery)
            ->whereYear('sales_orders.created_at', now()->year)
            ->whereMonth('sales_orders.created_at', now()->month)
            ->sum('closing_value');

        // Recent orders
        $recentOrders = (clone $ordersQuery)->with('product')->orderByDesc('id')->limit(5)->get();

        // Load card customization preferences
        $cardPreferences = $this->getCardPreferences($employee->id);

        return view('employee.dashboard', [
            'employee' => $employee,
            'viewMode' => $viewMode,
            'hasTeamAccess' => $hasTeamAccess,
            'activeGrant' => $activeGrant,
            'totalOrders' => $totalOrders,
            'closingValue' => number_format((float) $closingValue, 2),
            'verifiedRevenue' => number_format((float) $verifiedRevenue, 2),
            'customersCount' => $customersCount,
            'activeSubscriptions' => $activeSubscriptions,
            'pendingPaymentsCount' => $pendingPaymentsCount,
            'pendingAmount' => number_format((float) $pendingAmount, 2),
            'expiringSubscriptions' => $expiringSubscriptions,
            'followUpsCount' => $followUpsCount,
            'monthlyOrdersCount' => $monthlyOrdersCount,
            'monthlyClosingValue' => number_format((float) $monthlyClosingValue, 2),
            'recentOrders' => $recentOrders,
            'cardPreferences' => $cardPreferences,
        ]);
    }

    /**
     * Save employee dashboard card preferences.
     */
    public function savePreferences(Request $request): RedirectResponse|JsonResponse
    {
        $employee = $request->user();

        $validated = $request->validate([
            'cards' => ['required', 'array'],
            'cards.*.key' => ['required', 'string'],
            'cards.*.visible' => ['required'],
            'cards.*.order' => ['nullable', 'integer'],
        ]);

        $sanitized = [];
        foreach ($validated['cards'] as $index => $card) {
            $sanitized[] = [
                'key' => (string) $card['key'],
                'visible' => filter_var($card['visible'], FILTER_VALIDATE_BOOLEAN),
                'order' => isset($card['order']) ? (int) $card['order'] : ($index + 1),
            ];
        }

        UserUiPreference::setPreferences('employee', $employee->id, 'dashboard_cards', $sanitized);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Dashboard preferences saved successfully.']);
        }

        return back()->with('status', 'Dashboard cards updated.');
    }

    /**
     * Reset employee dashboard cards to default.
     */
    public function resetPreferences(Request $request): RedirectResponse|JsonResponse
    {
        $employee = $request->user();

        UserUiPreference::where('user_type', 'employee')
            ->where('user_id', $employee->id)
            ->where('category', 'dashboard_cards')
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Dashboard cards reset to default.']);
        }

        return back()->with('status', 'Dashboard cards reset to default.');
    }

    /**
     * Save employee sidebar modules preferences.
     */
    public function saveSidebarPreferences(Request $request): RedirectResponse|JsonResponse
    {
        $employee = $request->user();

        $validated = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*.key' => ['required', 'string'],
            'modules.*.visible' => ['required'],
            'modules.*.order' => ['nullable', 'integer'],
        ]);

        $sanitized = [];
        foreach ($validated['modules'] as $index => $mod) {
            $sanitized[] = [
                'key' => (string) $mod['key'],
                'visible' => filter_var($mod['visible'], FILTER_VALIDATE_BOOLEAN),
                'order' => isset($mod['order']) ? (int) $mod['order'] : ($index + 1),
            ];
        }

        UserUiPreference::setPreferences('employee', $employee->id, 'sidebar_modules', $sanitized);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Sidebar preferences saved successfully.']);
        }

        return back()->with('status', 'Sidebar navigation updated.');
    }

    /**
     * Reset employee sidebar modules to default.
     */
    public function resetSidebarPreferences(Request $request): RedirectResponse|JsonResponse
    {
        $employee = $request->user();

        UserUiPreference::where('user_type', 'employee')
            ->where('user_id', $employee->id)
            ->where('category', 'sidebar_modules')
            ->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Sidebar reset to default.']);
        }

        return back()->with('status', 'Sidebar reset to default.');
    }

    /**
     * Display Google Calendar module for employee.
     */
    public function calendar(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();
        $connection = EmployeeGoogleConnection::where('employee_id', $employee->id)->first();

        return view('employee.calendar', [
            'employee' => $employee,
            'connection' => $connection,
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

    /**
     * Retrieve sorted card preferences merged with defaults.
     */
    protected function getCardPreferences(int $employeeId): array
    {
        $saved = UserUiPreference::getPreferences('employee', $employeeId, 'dashboard_cards', []);
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
