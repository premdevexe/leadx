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

        // Prefill a wa.me share link for each row. prepare() only touches the
        // already-eager-loaded team/subscription relations, so this adds no
        // extra queries. Only share-eligible orders (active order + active
        // subscription + valid phone) get a link; the rest render without one.
        $shareLinks = [];
        foreach ($orders as $order) {
            $payload = $this->whatsapp->prepare($order);
            $shareLinks[$order->id] = $payload['eligible'] ? $payload['url'] : null;
        }

        return view('admin.orders.index', [
            'orders' => $orders,
            'shareLinks' => $shareLinks,
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
            'customer_phone_local' => ['nullable', 'string', 'max:30'],
            'customer_country_code' => ['nullable', 'string', 'max:10'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'duration' => ['sometimes', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            'product_value' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'closing_value' => ['nullable', 'numeric', 'min:0'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            // Only methods the admin has enabled in Settings may be chosen.
            'payment_method' => ['nullable', 'in:'.implode(',', \App\Models\Setting::enabledPaymentMethods())],
            'payment_status' => ['in:'.implode(',', SalesOrder::PAYMENT_STATUSES)],
            'order_status' => ['in:'.implode(',', SalesOrder::STATUSES)],
        ]);

        // Build E.164 phone from country code + local number
        $data = $this->normalizePhone($data);

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
        $order->loadMissing(['team', 'assignedEmployee', 'subscription', 'payments.verifiedByAdmin', 'product', 'refunds.admin', 'editHistories.admin', 'closingValueHistories']);

        $share = $this->whatsapp->prepare($order);

        $logs = WhatsappShareLog::where('order_id', $order->id)->latest('id')->take(10)->get();

        $credentials = app(\App\Services\OrderCredentialService::class);

        return view('admin.orders.show', [
            'order' => $order,
            'share' => $share,
            'logs' => $logs,
            'enabledPaymentMethods' => \App\Models\Setting::enabledPaymentMethods(),
            // Per-order customer API key status (masked — never the value).
            'apiKeyStatus' => $credentials->status($order),
            'apiKeyAvailable' => $credentials->hasKey($order),
            'revealedKey' => session('order_api_key_reveal'),
        ]);
    }

    /**
     * GET /admin/orders/{order}/edit
     */
    public function edit(SalesOrder $order): View
    {
        $order->loadMissing(['team', 'assignedEmployee', 'product']);

        $activeProducts = $this->orders->activeProducts();
        if ($order->product && ! $activeProducts->contains('id', $order->product_id)) {
            $activeProducts->push($order->product);
        }

        return view('admin.orders.edit', [
            'order' => $order,
            'employees' => User::where('role', 'employee')->orderBy('username')->get(['id', 'username', 'name', 'team_id']),
            'products' => $activeProducts,
            'orderStatuses' => SalesOrder::STATUSES,
            'paymentStatuses' => SalesOrder::PAYMENT_STATUSES,
        ]);
    }

    /**
     * PUT /admin/orders/{order}
     */
    public function update(Request $request, SalesOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'assigned_employee_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_phone_local' => ['nullable', 'string', 'max:30'],
            'customer_country_code' => ['nullable', 'string', 'max:10'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'duration' => ['required', 'in:'.implode(',', array_keys(SalesOrder::DURATIONS))],
            'product_value' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'closing_value' => ['nullable', 'numeric', 'min:0'],
            'amount_received' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['required', 'in:'.implode(',', SalesOrder::PAYMENT_STATUSES)],
            'order_status' => ['required', 'in:'.implode(',', SalesOrder::STATUSES)],
        ]);

        $productId = ! empty($data['product_id']) ? (int) $data['product_id'] : null;
        if ($productId) {
            $catalogProduct = Product::find($productId);
            if ($catalogProduct) {
                $data['product_id'] = $catalogProduct->id;
                $data['product_name_snapshot'] = $catalogProduct->name;
                $data['product_price_snapshot'] = $catalogProduct->price;
                $data['product_duration_snapshot'] = $catalogProduct->duration;
                $data['product_name'] = $catalogProduct->name;
                $data['product_value'] = $catalogProduct->price;
                $data['duration'] = $catalogProduct->duration;
            }
        } else {
            $data['product_id'] = null;
            $data['product_name_snapshot'] = $data['product_name'] ?? null;
            $data['product_price_snapshot'] = $data['product_value'] ?? 0;
            $data['product_duration_snapshot'] = $data['duration'] ?? '1y';
        }

        // Closing value is the rep's input; the discount is *derived* as the
        // gap between list price and closing price, never typed. A blank
        // closing value keeps the stored one (falling back to list price),
        // and the discount is re-derived whenever either side changes so the
        // stored figure can never drift from product_value - closing_value.
        $productVal = (float) (isset($data['product_value']) && $data['product_value'] !== ''
            ? $data['product_value']
            : ($order->product_value ?? 0));

        $closingVal = (isset($data['closing_value']) && $data['closing_value'] !== '')
            ? (float) $data['closing_value']
            : ((float) ($order->closing_value ?? 0) > 0 ? (float) $order->closing_value : $productVal);

        $data['closing_value'] = $closingVal;
        $data['discount_amount'] = round(max(0.0, $productVal - $closingVal), 2);
        $data['product_value'] = $productVal;

        // Normalize phone: combine country code + local number into E.164
        $this->normalizePhone($data);

        $editReason = $request->input('edit_reason', 'Order updated via Admin Panel');

        $order->fill($data);
        $dirty = $order->getDirty();

        foreach ($dirty as $field => $newValue) {
            if (in_array($field, ['updated_at'])) {
                continue;
            }
            $oldValue = $order->getOriginal($field);
            $order->editHistories()->create([
                'admin_id' => auth('admin')->id(),
                'field_name' => $field,
                'old_value' => is_scalar($oldValue) ? (string) $oldValue : json_encode($oldValue),
                'new_value' => is_scalar($newValue) ? (string) $newValue : json_encode($newValue),
                'reason' => $editReason,
            ]);
        }

        $order->save();

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Order '.$order->order_number.' updated successfully.');
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
            'currency' => \App\Models\Setting::currency(),
            'payment_date' => now(),
            'payment_status' => 'pending',
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        // A pending payment that still has room to verify prompts the admin
        // team to check it (non-blocking, admin-toggleable).
        try {
            app(\App\Services\EmailService::class)->dispatch('admin.payment_verify', [
                'order_id'       => $order->id,
                'order_number'   => $order->order_number,
                'customer_name'  => $order->customer_name,
                'customer_email' => $order->customer_email,
                'team_name'      => $order->team?->team_name,
                'product_name'   => $order->product_name_snapshot ?? '—',
                'closing_value'  => number_format((float) $order->closing_value, 2),
                'payment_method' => $data['payment_method'],
            ]);
        } catch (\Throwable) {
        }

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
        if ($order->closing_value_status === 'Requested') {
            return back()->with('error', 'Cannot activate order: Closing Value Approval is pending.');
        }

        if ($order->closing_value_status === 'Rejected') {
            return back()->with('error', 'Cannot activate order: Closing Value was rejected.');
        }

        if ($order->api_key_required_snapshot && ! \App\Services\OrderCredentialService::hasKey($order)) {
            return back()->with('error', 'Cannot activate order: An API key is required for this product before activation.');
        }

        $subscription = app(\App\Services\SubscriptionActivator::class)
            ->maybeActivate($order, $request->user('admin')?->id);

        if ($subscription === null) {
            return back()->with(
                'error',
                'Order '.$order->order_number.' was not activated: its verified payments do not yet satisfy the activation policy (see payment status "'.$order->payment_status.'").'
            );
        }

        return back()->with('status', 'Subscription activated ('.$subscription->status.' - expires '.$subscription->expires_at?->toDateString().').');
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

    /**
     * POST /admin/orders/{order}/closing-approval/accept
     */
    public function acceptClosingApproval(Request $request, SalesOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'adjusted_closing_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $update = [
            'closing_value_status' => 'Accepted',
            'closing_approval_actioned_at' => now(),
            'closing_approval_actioned_by' => auth('admin')->id(),
            'closing_approval_notes' => $data['notes'] ?? null,
        ];

        $oldVal = (float) $order->closing_value;

        if (! empty($data['adjusted_closing_value'])) {
            $newVal = (float) $data['adjusted_closing_value'];
            $update['closing_value'] = $newVal;
            $update['discount_amount'] = round(max(0.0, (float) $order->product_value - $newVal), 2);
        }

        $order->update($update);

        $order->closingValueHistories()->create([
            'actor_type' => 'admin',
            'actor_id' => auth('admin')->id(),
            'status' => 'Accepted',
            'previous_value' => $oldVal,
            'proposed_value' => $order->closing_value,
            'notes' => $data['notes'] ?? 'Accepted by Super Admin',
        ]);

        try {
            app(\App\Services\NotificationHubService::class)->notifyClosingApprovalDecision(
                $order,
                'accepted',
                $data['notes'] ?? null
            );
        } catch (\Throwable) {
        }

        return back()->with('status', "Closing value for order {$order->order_number} has been Accepted.");
    }

    /**
     * POST /admin/orders/{order}/closing-approval/reject
     */
    public function rejectClosingApproval(Request $request, SalesOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $oldVal = (float) $order->closing_value;

        $order->update([
            'closing_value_status' => 'Rejected',
            'order_status' => 'suspended',
            'closing_approval_actioned_at' => now(),
            'closing_approval_actioned_by' => auth('admin')->id(),
            'closing_approval_notes' => $data['rejection_reason'],
        ]);

        $order->closingValueHistories()->create([
            'actor_type' => 'admin',
            'actor_id' => auth('admin')->id(),
            'status' => 'Rejected',
            'previous_value' => $oldVal,
            'proposed_value' => $oldVal,
            'notes' => $data['rejection_reason'],
        ]);

        try {
            app(\App\Services\NotificationHubService::class)->notifyClosingApprovalDecision(
                $order,
                'rejected',
                $data['rejection_reason']
            );
        } catch (\Throwable) {
        }

        return back()->with('status', "Closing value for order {$order->order_number} has been Rejected.");
    }

    /**
     * POST /admin/orders/{order}/closing-value/modify (Section 4.3)
     */
    public function modifyAcceptedClosingValue(Request $request, SalesOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'new_closing_value' => ['required', 'numeric', 'min:0.01'],
            'modification_reason' => ['required', 'string', 'max:1000'],
        ]);

        $oldVal = (float) $order->closing_value;
        $newVal = round((float) $data['new_closing_value'], 2);
        $productVal = (float) ($order->product_price_snapshot ?? $order->product_value);
        $discountAmount = round(max(0.0, $productVal - $newVal), 2);

        $order->update([
            'closing_value' => $newVal,
            'discount_amount' => $discountAmount,
            'closing_approval_actioned_at' => now(),
            'closing_approval_actioned_by' => auth('admin')->id(),
            'closing_approval_notes' => $data['modification_reason'],
        ]);

        $order->closingValueHistories()->create([
            'actor_type' => 'admin',
            'actor_id' => auth('admin')->id(),
            'status' => 'Accepted',
            'previous_value' => $oldVal,
            'proposed_value' => $newVal,
            'notes' => $data['modification_reason'],
        ]);

        $order->editHistories()->create([
            'admin_id' => auth('admin')->id(),
            'field_name' => 'closing_value',
            'old_value' => (string) $oldVal,
            'new_value' => (string) $newVal,
            'reason' => $data['modification_reason'],
        ]);

        try {
            app(\App\Services\NotificationHubService::class)->notifyClosingApprovalDecision(
                $order,
                'accepted',
                'Closing value modified by Super Admin: ' . $data['modification_reason']
            );
        } catch (\Throwable) {
        }

        return back()->with('status', "Closing value for order {$order->order_number} modified to \${$newVal}.");
    }

    /**
     * POST /admin/orders/{order}/cancel (Phase 11)
     */
    public function cancelOrder(Request $request, SalesOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ]);

        $order->update([
            'order_status' => 'cancelled',
            'cancellation_reason' => $data['cancellation_reason'],
            'cancelled_at' => now(),
            'cancelled_by' => auth('admin')->id(),
        ]);

        if ($order->subscription) {
            $order->subscription->update(['status' => 'cancelled']);
        }

        try {
            app(\App\Services\NotificationHubService::class)->notifyOrderCancelled(
                $order,
                $data['cancellation_reason']
            );
        } catch (\Throwable) {
        }

        return back()->with('status', "Order {$order->order_number} has been cancelled.");
    }

    /**
     * POST /admin/orders/{order}/refunds (Phase 11 & P0)
     */
    public function processRefund(Request $request, SalesOrder $order): RedirectResponse
    {
        $refundable = $order->remainingRefundable();

        if ($refundable <= 0) {
            return back()->with('error', 'Cannot process refund: this order has no verified remaining refundable balance.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$refundable],
            'reason' => ['required', 'string', 'max:1000'],
            'type' => ['nullable', 'in:full,partial'],
            'status' => ['nullable', 'string', 'in:requested,recorded,processed,failed'],
            'gateway_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = round((float) $data['amount'], 2);
        $type = $data['type'] ?? ($amount >= $refundable ? 'full' : 'partial');
        $status = $data['status'] ?? 'processed';

        $refund = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $amount, $type, $status, $data, $refundable) {
            $lockedOrder = SalesOrder::where('id', $order->id)->lockForUpdate()->first();

            $currentRefundable = $lockedOrder->remainingRefundable();
            if ($amount > $currentRefundable) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Refund amount exceeds remaining refundable balance of $' . number_format($currentRefundable, 2),
                ]);
            }

            $refundRecord = $lockedOrder->refunds()->create([
                'admin_id' => auth('admin')->id(),
                'amount' => $amount,
                'type' => $type,
                'status' => $status,
                'gateway_reference' => $data['gateway_reference'] ?? null,
                'processed_at' => $status === 'processed' ? now() : null,
                'reason' => $data['reason'],
            ]);

            if ($status === 'processed') {
                $remaining = $lockedOrder->fresh()->remainingRefundable();

                if ($remaining <= 0.001) {
                    $lockedOrder->update(['payment_status' => 'refunded']);
                    if ($lockedOrder->subscription && $lockedOrder->subscription->status === 'active') {
                        $lockedOrder->subscription->update(['status' => 'cancelled']);
                    }
                } else {
                    $lockedOrder->update(['payment_status' => 'partially_refunded']);
                }
            }

            return $refundRecord;
        });

        if ($refund->status === 'processed') {
            try {
                app(\App\Services\NotificationHubService::class)->notifyRefundProcessed($refund);
            } catch (\Throwable) {
            }
        }

        return back()->with('status', 'Refund of $'.number_format($amount, 2).' (status: '.$refund->status.') recorded successfully.');
    }

    /**
     * Normalize the phone fields in $data in place.
     * Combines customer_country_code + customer_phone_local into a single
     * E.164-style customer_phone string, then removes the local field.
     */
    private function normalizePhone(array &$data): void
    {
        $local = $data['customer_phone_local'] ?? null;
        $cc = $data['customer_country_code'] ?? null;

        if ($local) {
            $digits = preg_replace('/\D/', '', $local);
            $data['customer_phone'] = $cc ? '+' . preg_replace('/\D/', '', $cc) . ltrim($digits, '0') : $local;
        } else {
            $data['customer_phone'] = null;
        }

        unset($data['customer_phone_local']);
    }
}