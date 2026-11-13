<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectPaymentRequest;
use App\Http\Requests\Admin\VerifyPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin payment verification & review.
 *
 * Access is enforced two ways (defense in depth): the route runs behind
 * `auth:sanctum` + `ensure-role-active:admin`, and the verify/reject
 * requests re-assert the admin role so a leaked team/employee token can
 * never verify or reject a payment.
 */
class PaymentsController extends Controller
{
    public function __construct(protected PaymentService $payments)
    {
    }

    /**
     * GET /api/v1/admin/payments
     *
     * ?status=pending|paid|partially_paid|failed|refunded
     * ?team_id=<id> &order_number=<search>
     */
    public function index(\Illuminate\Http\Request $request): AnonymousResourceCollection
    {
        $query = Payment::query()->with(['order', 'verifiedByAdmin']);

        if ($request->filled('status')) {
            $query->where('payment_status', $request->input('status'));
        }

        if ($request->filled('team_id')) {
            $query->whereHas('order', fn ($q) => $q->where('team_id', $request->integer('team_id')));
        }

        if ($request->filled('order_number')) {
            $query->whereHas('order', fn ($q) => $q->where('order_number', $request->input('order_number')));
        }

        return PaymentResource::collection(
            $query->latest()->paginate((int) $request->query('per_page', 25))
        );
    }

    /**
     * GET /api/v1/admin/payments/{payment}
     */
    public function show(int $paymentId): PaymentResource
    {
        $payment = Payment::with(['order.team', 'order.assignedEmployee', 'verifiedByAdmin', 'customer'])->findOrFail($paymentId);

        return new PaymentResource($payment);
    }

    /**
     * POST /api/v1/admin/payments/{payment}/verify
     *
     * Admin verification of a pending payment. Idempotent and
     * transaction-safe; drives CRM upsert + subscription activation
     * through the service.
     */
    public function verify(VerifyPaymentRequest $request, int $paymentId): JsonResponse
    {
        $payment = Payment::findOrFail($paymentId);
        $admin = $request->user('sanctum');

        // Access recording: who verified what, when.
        DB::table('audit_logs')->insert([
            'auditable_type' => $payment->getMorphClass(),
            'auditable_id' => $payment->id,
            'event' => 'payment.verified',
            'new_values' => json_encode(['admin_id' => $admin->id, 'at' => now()->toIso8601String()]),
            'ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updated = $this->payments->verify($payment, (int) $admin->id, $request->validated('notes'));

        return (new PaymentResource($updated->load('verifiedByAdmin', 'order')))->response();
    }

    /**
     * POST /api/v1/admin/payments/{payment}/reject
     */
    public function reject(RejectPaymentRequest $request, int $paymentId): JsonResponse
    {
        $payment = Payment::findOrFail($paymentId);
        $admin = $request->user('sanctum');

        DB::table('audit_logs')->insert([
            'auditable_type' => $payment->getMorphClass(),
            'auditable_id' => $payment->id,
            'event' => 'payment.rejected',
            'new_values' => json_encode(['admin_id' => $admin->id, 'at' => now()->toIso8601String()]),
            'ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updated = $this->payments->reject($payment, (int) $admin->id, $request->validated('notes'));

        return (new PaymentResource($updated->load('verifiedByAdmin', 'order')))->response();
    }

    /**
     * POST /api/v1/admin/payments
     *
     * Create a payment record (manual entry — e.g. UPI / bank transfer
     * reference an admin logs in). Starts pending; activation only
     * happens after the /verify endpoint.
     */
    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        $this->validate($request, [
            'order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'payment_method' => ['required', 'in:'.implode(',', Payment::METHODS)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'transaction_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'payment_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $data = $request->validated();

        $payment = Payment::create([
            'order_id' => $data['order_id'],
            'payment_method' => $data['payment_method'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'payment_date' => isset($data['payment_date']) ? \Illuminate\Support\Carbon::parse($data['payment_date']) : now(),
            'payment_status' => 'pending',
            'source' => 'manual',
        ]);

        return (new PaymentResource($payment->load('order')))->response()->setStatusCode(201);
    }
}
