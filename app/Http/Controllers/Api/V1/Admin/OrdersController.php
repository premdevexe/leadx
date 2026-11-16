<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Admin;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SubscriptionActivator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin order management — API mirror of the web /admin/orders panel.
 *
 * Every action mirrors the web controller's business rules 1:1 (closing
 * approval accept/reject, closing-value modification, cancellation,
 * refunds, payment recording) and stamps the acting admin from the Sanctum
 * token — never from a client-supplied id.
 */
class OrdersController extends Controller
{
    public function __construct(protected OrderService $orders)
    {
    }

    protected function admin(Request $request): Admin
    {
        /** @var Admin $admin */
        return $request->user('sanctum');
    }

    /**
     * GET /api/v1/admin/orders
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SalesOrder::query()->with(['team', 'assignedEmployee', 'subscription', 'product']);

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
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        return OrderResource::collection(
            $query->orderByDesc('id')->paginate(min(max((int) $request->input('per_page', 20), 1), 100))
        );
    }

    /**
     * GET /api/v1/admin/orders/{order}
     */
    public function show(Request $request, int $order): JsonResponse
    {
        $thisOrder = SalesOrder::with([
            'team', 'assignedEmployee', 'subscription', 'payments.verifiedByAdmin',
            'product', 'refunds.admin', 'editHistories.admin', 'closingValueHistories',
        ])->findOrFail($order);

        return response()->json(['data' => new OrderResource($thisOrder)]);
    }

    /**
     * POST /api/v1/admin/orders
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $request->validated();

        $team = Team::findOrFail($data['team_id']);

        // Same attribution rule as the web panel: when the admin does not
        // pick an employee, the first active employee of the team is used.
        $employee = ! empty($data['assigned_employee_id'])
            ? User::find($data['assigned_employee_id'])
            : User::where('team_id', $team->id)->where('status', 'active')->orderBy('id')->first();

        $order = $this->orders->createForTeam($team, $employee, $data);

        DB::table('audit_logs')->insert([
            'auditable_type' => SalesOrder::class,
            'auditable_id' => $order->id,
            'event' => 'order.created_by_admin',
            'new_values' => json_encode(['admin_id' => $admin->id]),
            'ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (new OrderResource($order->loadMissing(['team', 'subscription', 'product'])))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/admin/orders/{order}
     */
    public function update(Request $request, int $order): JsonResponse
    {
        $admin = $this->admin($request);
        $thisOrder = SalesOrder::findOrFail($order);

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

        // Catalog snapshots: when a catalog product is chosen, its values win.
        $productId = ! empty($data['product_id']) ? (int) $data['product_id'] : null;
        $catalog = $productId ? Product::find($productId) : null;
        if ($catalog) {
            $data['product_id'] = $catalog->id;
            $data['product_name_snapshot'] = $catalog->name;
            $data['product_price_snapshot'] = $catalog->price;
            $data['product_duration_snapshot'] = $catalog->duration;
            $data['product_name'] = $catalog->name;
            $data['product_value'] = $catalog->price;
            $data['duration'] = $catalog->duration;
        } else {
            $data['product_id'] = null;
            $data['product_name_snapshot'] = $data['product_name'] ?? null;
            $data['product_price_snapshot'] = $data['product_value'] ?? 0;
            $data['product_duration_snapshot'] = $data['duration'] ?? '1y';
        }

        // Derived closing value + discount (mirrors the web panel exactly).
        $productVal = (float) ((isset($data['product_value']) && $data['product_value'] !== '')
            ? $data['product_value']
            : ($thisOrder->product_value ?? 0));
        $closingVal = (isset($data['closing_value']) && $data['closing_value'] !== '')
            ? (float) $data['closing_value']
            : (((float) ($thisOrder->closing_value ?? 0) > 0 ? (float) $thisOrder->closing_value : $productVal));

        $data['closing_value'] = $closingVal;
        $data['discount_amount'] = round(max(0.0, $productVal - $closingVal), 2);
        $data['product_value'] = $productVal;

        $this->normalizePhone($data);
        unset($data['customer_phone_local']);

        $editReason = $request->input('edit_reason', 'Order updated via Admin API');

        $thisOrder->fill($data);
        $dirty = $thisOrder->getDirty();

        foreach ($dirty as $field => $newValue) {
            if ($field === 'updated_at') {
                continue;
            }
            $oldValue = $thisOrder->getOriginal($field);
            $thisOrder->editHistories()->create([
                'admin_id' => $admin->id,
                'field_name' => $field,
                'old_value' => is_scalar($oldValue) ? (string) $oldValue : json_encode($oldValue),
                'new_value' => is_scalar($newValue) ? (string) $newValue : json_encode($newValue),
                'reason' => $editReason,
            ]);
        }

        $thisOrder->save();

        return (new OrderResource($thisOrder->fresh()->loadMissing(['team', 'subscription', 'product'])))->response();
    }

    /**
     * POST /api/v1/admin/orders/{order}/payments
     */
    public function recordPayment(Request $request, int $order): JsonResponse
    {
        $admin = $this->admin($request);
        $thisOrder = SalesOrder::findOrFail($order);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:'.implode(',', array_keys(Setting::PAYMENT_METHODS))],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = Payment::create([
            'order_id' => $thisOrder->id,
            'source' => 'manual',
            'payment_method' => $data['payment_method'],
            'amount' => $data['amount'],
            'currency' => Setting::currency(),
            'payment_date' => now(),
            'payment_status' => 'pending',
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        try {
            app(\App\Services\EmailService::class)->dispatch('admin.payment_verify', [
                'order_id' => $thisOrder->id,
                'order_number' => $thisOrder->order_number,
                'customer_name' => $thisOrder->customer_name,
                'customer_email' => $thisOrder->customer_email,
                'team_name' => $thisOrder->team?->team_name,
                'product_name' => $thisOrder->product_name_snapshot ?? '—',
                'closing_value' => number_format((float) $thisOrder->closing_value, 2),
                'payment_method' => $data['payment_method'],
            ]);
        } catch (\Throwable) {
        }

        return response()->json([
            'message' => 'Payment recorded as pending verification.',
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
        ], 201);
    }

    /**
     * PUT /api/v1/admin/orders/{order}/activate
     */
    public function activate(Request $request, int $order): JsonResponse
    {
        $admin = $this->admin($request);
        $thisOrder = SalesOrder::findOrFail($order);

        if ($thisOrder->closing_value_status === 'Requested') {
            throw ValidationException::withMessages([
                'order' => 'Cannot activate order: Closing Value Approval is pending.',
            ]);
        }
        if ($thisOrder->closing_value_status === 'Rejected') {
            throw ValidationException::withMessages([
                'order' => 'Cannot activate order: Closing Value was rejected.',
            ]);
        }
        if ($thisOrder->api_key_required_snapshot && ! \App\Services\OrderCredentialService::hasKey($thisOrder)) {
            throw ValidationException::withMessages([
                'order' => 'Cannot activate order: an API key is required for this product before activation.',
            ]);
        }

        $subscription = app(SubscriptionActivator::class)->maybeActivate($thisOrder, $admin->id);

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'payment_status' => "Order {$thisOrder->order_number} was not activated: its verified payments do not yet satisfy the activation policy (payment status \"{$thisOrder->payment_status}\").",
            ]);
        }

        return response()->json([
            'message' => "Subscription activated ({$subscription->status} - expires {$subscription->expires_at?->toDateString()}).",
            'data' => new OrderResource($thisOrder->fresh()->loadMissing(['team', 'subscription', 'product'])),
        ]);
    }

    /**
     * POST /api/v1/admin/orders/{order}/closing-approval/accept
     */
    public function acceptClosingApproval(Request $request, int $order): JsonResponse
    {
        $admin = $this->admin($request);
        $thisOrder = SalesOrder::findOrFail($order);

        $data = $request->validate([
            'adjusted_closing_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $update = [
            'closing_value_status' => 'Accepted',
            'closing_approval_actioned_at' => now(),
            'closing_approval_actioned_by' => $admin->id,
            'closing_approval_notes' => $data['notes'] ?? null,
        ];

        $oldVal = (float) $thisOrder->closing_value;

        if (! empty($data['adjusted_closing_value'])) {
            $newVal = (float) $data['adjusted_closing_value'];
            $update['closing_value'] = $newVal;
            $update['discount_amount'] = round(max(0.0, (float) $thisOrder->product_value - $newVal), 2);
        }

        $thisOrder->update($update);

        $thisOrder->closingValueHistories()->create([
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'status' => 'Accepted',
            'previous_value' => $oldVal,
            'proposed_value' => $thisOrder->closing_value,
            'notes' => $data['notes'] ?? 'Accepted by Super Admin',
        ]);

        try {
            app(\App\Services\NotificationHubService::class)
                ->notifyClosingApprovalDecision($thisOrder, 'accepted', $data['notes'] ?? null);
        } catch (\Throwable) {
        }

        return response()->json([
            'message' => "Closing value for order {$thisOrder->order_number} has been Accepted.",
            'data' => new OrderResource($thisOrder->fresh()),
        ]);
    }

    /**
     * POST /api/v1/admin/orders/{order}/closing-approval/reject
     */
    public function rejectClosingApproval(Request $request, int $order): JsonResponse
    {
        $admin = $this->admin($request);
        $thisOrder = SalesOrder::findOrFail($order);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $oldVal = (float) $thisOrder->closing_value;

        $thisOrder->update([
            'closing_value_status' => 'Rejected',
            'order_status' => 'suspended',
            'closing_approval_actioned_at' => now(),
            'closing_approval_actioned_by' => $admin->id,
            'closing_approval_notes' => $data['rejection_reason'],
        ]);

        $thisOrder->closingValueHistories()->create([
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'status' => 'Rejected',
            'previous_value' => $oldVal,
            'proposed_value' => $oldVal,
            'notes' => $data['rejection_reason'],
        ]);

        try {
            app(\App\Services\NotificationHubService::class)
                ->notifyClosingApprovalDecision($thisOrder, 'rejected', $data['rejection_reason']);
        } catch (\Throwable) {
        }

        return response()->json([
            'message' => "Closing value for order {$thisOrder->order_number} has been Rejected.",
            'data' => new OrderResource($thisOrder->fresh()),
        ]);
    }

    /**
     * POST /api/v1/admin/orders/{order}/closing-value/modify
     *
     * Section 4.3: adjust an already-Accepted closing value (volume
     * exception) while preserving full audit history.
     */
    public function modifyAcceptedClosingValue(Request $request, int $order): JsonResponse
    {
        $admin = $this->admin($request);
        $thisOrder = SalesOrder::findOrFail($order);

        $data = $request->validate([
            'new_closing_value' => ['required', 'numeric', 'min:0.01'],
            'modification_reason' => ['required', 'string', 'max:1000'],
        ]);

        $oldVal = (float) $thisOrder->closing_value;
        $newVal = round((float) $data['new_closing_value'], 2);
        $productVal = (float) ($thisOrder->product_price_snapshot ?? $thisOrder->product_value);
        $discountAmount = round(max(0.0, $productVal - $newVal), 2);

        $thisOrder->update([
            'closing_value' => $newVal,
            'discount_amount' => $discountAmount,
            'closing_approval_actioned_at' => now(),
            'closing_approval_actioned_by' => $admin->id,
            'closing_approval_notes' => $data['modification_reason'],
        ]);

        $thisOrder->closingValueHistories()->create([
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'status' => 'Accepted',
            'previous_value' => $oldVal,
            'proposed_value' => $newVal,
            'notes' => $data['modification_reason'],
        ]);

        $thisOrder->editHistories()->create([
            'admin_id' => $admin->id,
            'field_name' => 'closing_value',
            'old_value' => (string) $oldVal,
            'new_value' => (string) $newVal,
            'reason' => $data['modification_reason'],
        ]);

        try {
            app(\App\Services\NotificationHubService::class)->notifyClosingApprovalDecision(
                $thisOrder,
                'accepted',
                'Closing value modified by Super Admin: '.$data['modification_reason']
            );
        } catch (\Throwable) {
        }

        return response()->json([
            'message' => "Closing value for order {$thisOrder->order_number} modified to \${$newVal}.",
            'data' => new OrderResource($thisOrder->fresh()),
        ]);
    }

    /**
     * POST /api/v1/admin/orders/{order}/cancel
     */
    public function cancel(Request $request, int $order): JsonResponse
    {
        $admin = $this->admin($request);
        $thisOrder = SalesOrder::findOrFail($order);

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ]);

        $reason = $data['cancellation_reason'];
        $cancelledBy = $admin->id;

        DB::transaction(function () use ($thisOrder, $reason, $cancelledBy): void {
            $locked = SalesOrder::where('id', $thisOrder->id)->lockForUpdate()->first();

            $locked->update([
                'order_status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by' => $cancelledBy,
            ]);

            if ($locked->subscription) {
                $locked->subscription->update(['status' => 'cancelled']);
            }
        });

        try {
            app(\App\Services\NotificationHubService::class)->notifyOrderCancelled($thisOrder, $reason);
        } catch (\Throwable) {
        }

        return response()->json([
            'message' => "Order {$thisOrder->order_number} has been cancelled.",
            'data' => new OrderResource($thisOrder->fresh()),
        ]);
    }

    /**
     * POST /api/v1/admin/orders/{order}/refunds
     */
    public function processRefund(Request $request, int $order): JsonResponse
    {
        $admin = $this->admin($request);
        $thisOrder = SalesOrder::findOrFail($order);

        $refundable = $thisOrder->remainingRefundable();

        if ($refundable <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Cannot process refund: this order has no verified remaining refundable balance.',
            ]);
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

        $refund = DB::transaction(function () use ($thisOrder, $admin, $amount, $type, $status, $data) {
            $locked = SalesOrder::where('id', $thisOrder->id)->lockForUpdate()->first();

            $currentRefundable = $locked->remainingRefundable();
            if ($amount > $currentRefundable) {
                throw ValidationException::withMessages([
                    'amount' => "Refund amount exceeds remaining refundable balance of $".number_format($currentRefundable, 2),
                ]);
            }

            $refundRecord = $locked->refunds()->create([
                'admin_id' => $admin->id,
                'amount' => $amount,
                'type' => $type,
                'status' => $status,
                'gateway_reference' => $data['gateway_reference'] ?? null,
                'processed_at' => $status === 'processed' ? now() : null,
                'reason' => $data['reason'],
            ]);

            if ($status === 'processed') {
                $remaining = $locked->fresh()->remainingRefundable();

                if ($remaining <= 0.001) {
                    $locked->update(['payment_status' => 'refunded']);
                    if ($locked->subscription && $locked->subscription->status === 'active') {
                        $locked->subscription->update(['status' => 'cancelled']);
                    }
                } else {
                    $locked->update(['payment_status' => 'partially_refunded']);
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

        return response()->json([
            'message' => "Refund of $".number_format($amount, 2)." (status: {$refund->status}) recorded successfully.",
            'refund' => $refund,
        ], 201);
    }

    /**
     * Combine country code + local number into E.164 (mirrors the web panel).
     */
    protected function normalizePhone(array &$data): void
    {
        $local = $data['customer_phone_local'] ?? ($data['customer_phone'] ?? null);
        $cc = $data['customer_country_code'] ?? null;

        if ($local && ! $cc && str_starts_with($local, '+')) {
            $data['customer_phone'] = $local;
        } elseif ($local) {
            $digits = preg_replace('/\D/', '', $local);
            $data['customer_phone'] = $cc ? '+'.preg_replace('/\D/', '', $cc).ltrim($digits, '0') : $local;
        } else {
            $data['customer_phone'] = null;
        }

        unset($data['customer_phone_local'], $data['customer_country_code']);
    }
}
