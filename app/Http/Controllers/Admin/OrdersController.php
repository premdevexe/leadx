<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappShareLog;
use App\Services\OrderService;
use App\Services\WhatsappShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-facing (server-rendered) order management.
 *
 * Uses the same OrderService / WhatsappShareService as the API, so admin
 * and team/employee flows stay behaviourally identical. Product identity,
 * price and duration are resolved by OrderService from the catalog: the
 * client may only submit a product_id, never its values.
 */
class OrdersController extends Controller
{
    public function __construct(protected OrderService $orders, protected WhatsappShareService $whatsapp)
    {
    }

    /**
     * GET /admin/orders - all orders with team/employee/status/number filters.
     */
    public function index(Request $request): View
    {
        $query = SalesOrder::query()->with(['team', 'assignedEmployee', 'subscription']);

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->input('order_status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('order_number')) {
            $term = str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->query('order_number'));
            $query->where('order_number', 'like', '%'.$term.'%');
        }

        $orders = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'teams' => Team::orderBy('team_code')->get(['id', 'team_code', 'team_name']),
            'employees' => User::where('role', 'employee')->orderBy('username')->get(['id', 'username', 'name', 'team_id']),
            'filters' => [
                'team_id' => $request->integer('team_id') ?: null,
                'employee_id' => $request->integer('employee_id') ?: null,
                'order_status' => $request->input('order_status'),
                'payment_status' => $request->input('payment_status'),
                'order_number' => $request->input('order_number'),
            ],
        ]);
    }

    /**
     * GET /admin/orders/create
     */
    public function create(): View
    {
        return view('admin.orders.create', [
            'teams' => Team::orderBy('team_code')->get(['id', 'team_code', 'team_name']),
            'employees' => User::where('role', 'employee')->orderBy('username')->get(['id', 'username', 'name', 'team_id']),
            'products' => $this->orders->activeProducts(),
            'paymentMethods' => \App\Models\Setting::PAYMENT_METHODS,
            'enabledPaymentMethods' => \App\Models\Setting::enabledPaymentMethods(),
            'team' => null,
        ]);
    }

    /**
     * POST /admin/orders
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'assigned_employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'subscription_plan' => ['nullable', 'string', 'max:50'],
            'duration' => ['sometimes', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            'product_value' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'closing_value' => ['nullable', 'numeric', 'min:0'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['in:'.implode(',', SalesOrder::PAYMENT_STATUSES)],
            'order_status' => ['in:'.implode(',', SalesOrder::STATUSES)],
        ]);

        $team = Team::find($data['team_id']);

        // When a catalog product is chosen, OrderService resolves its
        // authoritative name/price/duration from the database and rejects
        // inactive products - client-submitted values for those fields are
        // ignored. Manual orders keep their submitted values.
        // Attribution: an admin-created order defaults to the first active
        // employee of the team when none is explicitly chosen.
        $employee = ! empty($data['assigned_employee_id'])
            ? User::find($data['assigned_employee_id'])
            : User::where('team_id', $team->id)->orderBy('id')->first();

        $order = $this->orders->createForTeam($team, $employee, $data);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Order '.$order->order_number.' created.');
    }

    /**
     * GET /admin/orders/{order} - detail + WhatsApp share panel.
     */
    public function show(SalesOrder $order): View
    {
        $order->loadMissing(['team', 'assignedEmployee', 'subscription', 'payments.verifiedByAdmin', 'product']);

        $share = $this->whatsapp->prepare($order);

        $logs = WhatsappShareLog::where('order_id', $order->id)->latest('id')->take(10)->get();

        return view('admin.orders.show', [
            'order' => $order,
            'share' => $share,
            'logs' => $logs,
            'enabledPaymentMethods' => \App\Models\Setting::enabledPaymentMethods(),
        ]);
    }

    /**
     * POST /admin/orders/{order}/payments
     */
    public function recordPayment(Request $request, SalesOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = \App\Models\Payment::create([
            'order_id' => $order->id,
            'source' => 'manual',
            'payment_method' => $data['payment_method'],
            'amount' => $data['amount'],
            'currency' => 'USD',
            'payment_date' => now(),
            'payment_status' => 'pending',
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        return back()->with('status', 'Payment #'.$payment->id.' of '.number_format((float) $payment->amount, 2).' recorded as pending verification.');
    }

    /**
     * POST /admin/orders/{order}/payments/{payment}/verify
     */
    public function verifyPayment(Request $request, SalesOrder $order, \App\Models\Payment $payment): RedirectResponse
    {
        $admin = $request->user('admin');
        app(\App\Services\PaymentService::class)->verify(
            $payment,
            $admin->id,
            $request->input('notes', 'Verified via Admin Panel')
        );

        return back()->with('status', 'Payment #'.$payment->id.' verified. Associated order and subscription updated.');
    }

    /**
     * POST /admin/orders/{order}/payments/{payment}/reject
     */
    public function rejectPayment(Request $request, SalesOrder $order, \App\Models\Payment $payment): RedirectResponse
    {
        $admin = $request->user('admin');
        app(\App\Services\PaymentService::class)->reject(
            $payment,
            $admin->id,
            $request->input('notes', 'Rejected via Admin Panel')
        );

        return back()->with('status', 'Payment #'.$payment->id.' rejected.');
    }

    /**
     * PUT /admin/orders/{order}/activate
     */
    public function activate(Request $request, SalesOrder $order): RedirectResponse
    {
        if ($order->payment_status !== 'paid') {
            return back()->with(
                'error',
                'Order '.$order->order_number.' cannot be activated: payment status is "'.$order->payment_status.'", not "paid".'
            );
        }

        $subscription = Subscription::updateOrCreate(
            ['order_id' => $order->id],
            [
                'team_id' => $order->team_id,
                'status' => 'active',
                'started_at' => now(),
                'expires_at' => SalesOrder::expiryFor((string) ($order->duration ?: '1y')),
            ]
        );

        $order->forceFill(['order_status' => 'active'])->save();

        return back()->with('status', 'Subscription activated ('.$subscription->status.' - expires '.$subscription->expires_at->toDateString().').');
    }

    /**
     * POST /admin/orders/{order}/whatsapp-share-log
     */
    public function recordWhatsappShareLog(Request $request, SalesOrder $order): RedirectResponse
    {
        $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->whatsapp->logShare($order, $request->user('admin'), $request->input('message'));

        return back()->with('status', 'WhatsApp share recorded for '.$order->order_number.'. The server did not send anything - open the link on your device to send it.');
    }
}