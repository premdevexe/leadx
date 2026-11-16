<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\OrderCredentialService;
use App\Services\OrderService;
use App\Services\SubscriptionActivator;
use App\Services\WhatsappShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

/**
 * Employee orders — mirrors the employee web panel's My Orders page:
 * paginated listing/detail with my_data/team_data scoping, and the
 * order actions an employee performs on the panel (create, complete,
 * resubmit closing value, API key, WhatsApp share, activate).
 *
 * Writes are owned by the employee's own identity: a created order is always
 * assigned to the caller, and mutating actions are refused on orders the
 * employee does not directly own.
 */
class OrdersController extends Controller
{
    use EmployeeScoped;

    public function __construct(
        protected OrderService $orders,
        protected OrderCredentialService $credentials,
        protected WhatsappShareService $whatsapp,
    ) {
    }

    /**
     * GET /api/v1/employee/orders
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $resolved = $this->resolveView($employee, $request->input('view'));
        $orders = $this->scopedOrders($employee, $resolved['view'], $resolved['grant'])
            ->with(['product', 'assignedEmployee', 'subscription', 'team'])
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return response()->json([
            'meta' => $this->listMeta($resolved, $orders),
            'data' => OrderResource::collection($orders->items())->resolve(),
        ]);
    }

    /**
     * POST /api/v1/employee/orders
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        if (! $employee->team_id) {
            throw ValidationException::withMessages([
                'team_id' => 'You are not assigned to a team and cannot create orders.',
            ]);
        }

        // An employee creates orders under their own team only. A client
        // that sends a different team_id is rejected (same rule as the
        // shared /api/v1/orders endpoint) — never silently ignored.
        $requestedTeamId = (int) $request->input('team_id', 0);
        if ($requestedTeamId > 0 && $requestedTeamId !== (int) $employee->team_id) {
            throw ValidationException::withMessages([
                'team_id' => 'You may only create orders under your assigned team.',
            ]);
        }

        $team = \App\Models\Team::findOrFail($employee->team_id);

        // The order is always assigned to the caller, never to a
        // client-supplied employee id.
        $validated = $request->validated();
        unset($validated['assigned_employee_id']);
        $validated['customer_phone'] = $this->normalizeCustomerPhone($request, $validated);

        $order = $this->orders->createForTeam($team, $employee, $validated);

        return (new OrderResource($order->loadMissing(['team', 'subscription', 'product'])))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/employee/orders/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $order = SalesOrder::with(['product', 'subscription', 'payments', 'team', 'assignedEmployee'])->findOrFail($id);
        $isOwned = $this->assertOrderAccess($employee, $order);

        $whatsappShare = $this->whatsapp->prepare(
            $order,
            $isOwned && $employee->hasPermission('orders.view_api_key')
        );

        return response()->json([
            'data' => new OrderResource($order),
            'view_only' => ! $isOwned,
            'api_key_status' => $this->credentials->status($order),
            'whatsapp_share' => $whatsappShare,
        ]);
    }

    /**
     * POST /api/v1/employee/orders/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $order = SalesOrder::findOrFail($id);
        $this->assertOwnedOrder($employee, $order);
        abort_unless($employee->hasPermission('orders.complete'), 403, 'You do not have permission to complete orders.');

        if ($order->closing_value_status === 'Requested' || $order->closing_value_status === 'Rejected') {
            throw ValidationException::withMessages([
                'order' => "Cannot complete order: Closing Value Approval is {$order->closing_value_status}.".
                    ' '.($order->closing_value_status === 'Rejected' ? 'Resubmit a new value first.' : 'Wait for admin approval.'),
            ]);
        }

        if ($order->api_key_required_snapshot && ! OrderCredentialService::hasKey($order)) {
            throw ValidationException::withMessages([
                'order' => 'Cannot complete order: A customer API key is required for this product.',
            ]);
        }

        if (in_array($order->order_status, ['active', 'confirmed'], true)) {
            return response()->json([
                'message' => "Order {$order->order_number} is already completed.",
                'data' => new OrderResource($order->loadMissing(['team', 'subscription', 'product'])),
            ]);
        }

        $order->update(['order_status' => 'confirmed']);

        $subscription = app(SubscriptionActivator::class)->maybeActivate($order, $employee->id);

        return response()->json([
            'message' => $subscription
                ? "Order {$order->order_number} completed and subscription activated!"
                : "Order {$order->order_number} status updated to confirmed.",
            'data' => new OrderResource($order->fresh()->loadMissing(['team', 'subscription', 'product'])),
        ]);
    }

    /**
     * POST /api/v1/employee/orders/{id}/closing-approval/resubmit
     */
    public function resubmitClosingValue(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $order = SalesOrder::findOrFail($id);
        $this->assertOwnedOrder($employee, $order);

        $minFloor = (float) ($order->minimum_closing_price_snapshot ?? 0);

        $data = $request->validate([
            'closing_value' => ['required', 'numeric', 'min:'.($minFloor > 0 ? $minFloor : 0.01)],
            'resubmission_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldVal = (float) $order->closing_value;
        $newVal = round((float) $data['closing_value'], 2);
        $productVal = (float) ($order->product_price_snapshot ?? $order->product_value);
        $discountAmount = round(max(0.0, $productVal - $newVal), 2);

        $order->update([
            'closing_value' => $newVal,
            'discount_amount' => $discountAmount,
            'closing_value_status' => 'Requested',
            'order_status' => $order->order_status === 'suspended' ? 'pending' : $order->order_status,
            'closing_approval_requested_at' => now(),
            'closing_approval_notes' => $data['resubmission_notes'] ?? 'Resubmitted after rejection',
        ]);

        $order->closingValueHistories()->create([
            'actor_type' => 'employee',
            'actor_id' => $employee->id,
            'status' => 'Requested',
            'previous_value' => $oldVal,
            'proposed_value' => $newVal,
            'notes' => $data['resubmission_notes'] ?? 'Resubmitted by employee after rejection',
        ]);

        try {
            app(\App\Services\NotificationHubService::class)->notifyClosingApprovalRequested($order);
        } catch (\Throwable) {
        }

        return response()->json([
            'message' => 'Closing value resubmitted for Super Admin approval.',
            'data' => new OrderResource($order->fresh()->loadMissing(['team', 'product'])),
        ]);
    }

    /**
     * POST /api/v1/employee/orders/{id}/api-key — reveal the stored key.
     *
     * Storing keys is admin-only (the web panel enforces the same rule); the
     * employee can only reveal, and only when they own the order AND hold
     * the orders.view_api_key permission. The plaintext is never logged.
     */
    public function revealApiKey(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $order = SalesOrder::findOrFail($id);
        $this->assertOwnedOrder($employee, $order);

        if (! $employee->hasPermission('orders.view_api_key')) {
            abort(403, 'You do not have permission to view API keys.');
        }

        $key = $this->credentials->reveal($order, $employee);
        if ($key === null) {
            throw ValidationException::withMessages([
                'api_key' => 'No API key is stored for this order, or it could not be decrypted.',
            ]);
        }

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'api_key' => $key,
        ]);
    }

    /**
     * GET /api/v1/employee/orders/{id}/whatsapp-share
     */
    public function whatsappShare(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $order = SalesOrder::findOrFail($id);
        $isOwned = $this->assertOrderAccess($employee, $order);

        $includeKey = $request->boolean('include_api_key')
            && $isOwned
            && $this->credentials->canManage($order, $employee);

        $payload = $this->whatsapp->prepare($order, $includeKey);

        return response()->json([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'eligible' => $payload['eligible'],
            'reason' => $payload['reason'],
            'phone' => $payload['phone'],
            'message' => $payload['message'],
            'url' => $payload['url'],
            'api_key_included' => $payload['api_key_included'],
        ]);
    }

    /**
     * POST /api/v1/employee/orders/{id}/activate
     *
     * Activates the order as a paid subscription only when verified
     * payments satisfy the activation policy. Idempotent — a repeated call
     * never mints a duplicate subscription.
     */
    public function activate(Request $request, int $id): JsonResource
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $order = SalesOrder::findOrFail($id);
        $this->assertOrderAccess($employee, $order);

        $subscription = app(SubscriptionActivator::class)->maybeActivate($order, $employee->id);

        if ($subscription === null) {
            throw ValidationException::withMessages([
                'payment_status' => 'An order may only become an active subscription once its verified payments satisfy the activation policy.',
            ]);
        }

        return (new OrderResource($order->fresh()->loadMissing(['team', 'subscription', 'product'])))->response();
    }

    /**
     * Merge the optional local-phone + country-code pair into a normalized
     * phone, exactly as the web create-order page does. Both extra inputs
     * are read raw (the shared StoreOrderRequest validates the same field
     * set the admin/team APIs use and ignores them).
     */
    protected function normalizeCustomerPhone(Request $request, array $validated): ?string
    {
        if (! empty($validated['customer_phone'])) {
            return $validated['customer_phone'];
        }

        $local = $request->input('customer_phone_local');
        $cc = $request->input('customer_country_code');

        if ($local) {
            $digits = preg_replace('/\D/', '', $local);

            return $cc ? '+' . preg_replace('/\D/', '', $cc) . ltrim($digits, '0') : $local;
        }

        return null;
    }
}
