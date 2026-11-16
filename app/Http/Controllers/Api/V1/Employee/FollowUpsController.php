<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Http\Controllers\Controller;
use App\Http\Requests\FollowUp\StoreFollowUpRequest;
use App\Http\Resources\FollowUpHistoryResource;
use App\Http\Resources\FollowUpResource;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\FollowUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Employee follow-ups — mirrors the web panel's Follow-ups page. Reads are
 * scoped by {@see FollowUpService} (an employee only ever sees follow-ups
 * assigned to themselves); writes are owned by the caller and validated
 * against the employee's verified customers, exactly as the web store does.
 *
 * Update/complete/reschedule/cancel reuse the service commands, and the
 * shared request class so the rules stay in one place. The web panel has
 * no hard delete (cancelled follow-ups keep their history), so the API
 * DELETE performs a managed cancel through the same service command.
 */
class FollowUpsController extends Controller
{
    use EmployeeScoped;

    public function __construct(protected FollowUpService $followUps)
    {
    }

    /**
     * GET /api/v1/employee/follow-ups
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $query = FollowUp::query()->with(['customer', 'team', 'assignedEmployee', 'order']);
        $this->followUps->scopeForActor($query, $employee);

        if ($request->filled('status') && in_array($request->input('status'), FollowUp::STATUSES, true)) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('follow_up_type')) {
            $query->where('follow_up_type', $request->input('follow_up_type'));
        }

        $followUps = $query->orderByDesc('scheduled_at')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return response()->json([
            'meta' => [
                'current_page' => $followUps->currentPage(),
                'last_page' => $followUps->lastPage(),
                'per_page' => $followUps->perPage(),
                'total' => $followUps->total(),
                // The open (scheduled + rescheduled) count the panel surfaces.
                'open_count' => FollowUp::forEmployee($employee->id)
                    ->whereIn('status', ['scheduled', 'rescheduled'])
                    ->count(),
            ],
            'data' => FollowUpResource::collection($followUps->items())->resolve(),
        ]);
    }

    /**
     * POST /api/v1/employee/follow-ups
     */
    public function store(StoreFollowUpRequest $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $data = $request->validated();

        // The web store additionally requires the customer to be one of the
        // employee's verified customers.
        $authorized = $this->authorizedCustomerIds($employee);

        if (! in_array((int) ($data['customer_id'] ?? 0), array_map('intval', $authorized), true)) {
            throw ValidationException::withMessages([
                'customer_id' => 'Select a verified customer associated with your orders.',
            ]);
        }

        // The web store additionally requires a scheduled_at on create; the
        // API does the same so a follow-up is always actionable.
        if (blank($data['scheduled_at'] ?? null)) {
            $data['scheduled_at'] = now();
        }

        // An employee always self-assigns; the service enforces the same.
        $followUp = $this->followUps->store($employee, $data);

        return (new FollowUpResource($followUp->loadMissing(['customer', 'team', 'assignedEmployee', 'order'])))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/employee/follow-ups/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $followUp = FollowUp::with(['customer', 'assignedEmployee', 'order', 'history'])->findOrFail($id);
        $this->followUps->assertCanView($followUp, $employee);

        return response()->json([
            'data' => new FollowUpResource($followUp),
            'history' => [
                'data' => FollowUpHistoryResource::collection(
                    $followUp->history()->latest('created_at')->latest('id')->get()
                ),
            ],
            'can_manage' => app(\App\Services\PermissionService::class)->hasPermission($employee, 'follow_ups.edit')
                && (int) $followUp->assigned_employee_id === (int) $employee->id,
        ]);
    }

    /**
     * PUT /api/v1/employee/follow-ups/{id}
     *
     * Body-driven transition: {action: complete|reschedule|cancel, ...}
     * so a single endpoint covers the web panel's three state commands
     * without changing any existing URL.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $followUp = FollowUp::findOrFail($id);
        $action = $request->input('action');

        $data = $request->validate([
            'action' => ['required', Rule::in(['complete', 'reschedule', 'cancel'])],
            'outcome' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'scheduled_at' => ['required_if:action,reschedule', 'nullable', 'date'],
            'next_follow_up_at' => ['sometimes', 'nullable', 'date'],
        ]);

        switch ($data['action']) {
            case 'complete':
                $this->followUps->complete($followUp, $employee, $data['outcome'] ?? null, $data['notes'] ?? null);
                break;

            case 'reschedule':
                $this->followUps->reschedule(
                    $followUp,
                    $employee,
                    $data['scheduled_at'],
                    $data['next_follow_up_at'] ?? null,
                    $data['notes'] ?? null
                );
                break;

            case 'cancel':
                $this->followUps->cancel($followUp, $employee, $data['notes'] ?? null);
                break;
        }

        return response()->json([
            'data' => new FollowUpResource($followUp->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/employee/follow-ups/{id}
     *
     * Follow-ups are not hard-deleted anywhere in LeadX (history must be
     * retained), so a DELETE performs a managed cancel via the same service
     * command — the row stays, its status becomes `cancelled`.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $followUp = FollowUp::findOrFail($id);

        if ($followUp->status === 'cancelled') {
            return response()->json(['message' => 'Follow-up is already cancelled.', 'data' => new FollowUpResource($followUp)]);
        }

        $this->followUps->cancel($followUp, $employee, 'Cancelled via API.');

        return response()->json([
            'message' => 'Follow-up cancelled. Its history has been retained.',
            'data' => new FollowUpResource($followUp->fresh()),
        ]);
    }

    /**
     * Verified customer ids the employee may attach follow-ups to — same
     * set the web picker offers.
     *
     * @return list<int>
     */
    protected function authorizedCustomerIds(User $employee): array
    {
        $orders = SalesOrder::where('assigned_employee_id', $employee->id);

        $query = Customer::query()->verifiedPurchases();

        $customers = $query->where('team_id', $employee->team_id)->where(function ($q) use ($orders, $employee) {
            $q->whereIn('phone', (clone $orders)->whereNotNull('customer_phone')->select('customer_phone'))
                ->orWhereIn('email', (clone $orders)->whereNotNull('customer_email')->select('customer_email'))
                ->orWhereHas('subscriptions.order', fn ($o) => $o->where('assigned_employee_id', $employee->id))
                ->orWhereHas('payments.order', fn ($o) => $o->where('assigned_employee_id', $employee->id));
        })->pluck('id');

        return $customers->all();
    }
}
