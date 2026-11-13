<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FollowUp\StoreFollowUpRequest;
use App\Http\Requests\FollowUp\UpdateFollowUpRequest;
use App\Http\Resources\FollowUpHistoryResource;
use App\Http\Resources\FollowUpResource;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Team;
use App\Models\User;
use App\Services\FollowUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

/**
 * Customer follow-up management + Google Calendar sync (Phase 08,
 * Modules 1 & 3). Access is scoped by the actor's role inside
 * {@see FollowUpService}.
 */
class FollowUpsController extends Controller
{
    public function __construct(protected FollowUpService $followUps)
    {
    }

    /**
     * GET /customers/verified
     *
     * The follow-up picker: CRM customers with at least one verified
     * purchase (Module 1, rule 1). Scoped like every other read — a team
     * sees its own customers, an employee the customers of their team,
     * an admin sees all.
     */
    public function verifiedCustomers(Request $request): AnonymousResourceCollection
    {
        $query = Customer::query()->verifiedPurchases();

        $actor = $request->user('sanctum');

        if ($actor instanceof Team) {
            $query->where('team_id', $actor->id);
        } elseif ($actor instanceof User) {
            $query->where('team_id', $actor->team_id);
        }

        if ($request->filled('team_id') && $actor instanceof \App\Models\Admin) {
            $query->where('team_id', $request->integer('team_id'));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%'));
        }

        return \App\Http\Resources\CustomerResource::collection($query->orderBy('name')->get());
    }

    /**
     * GET /follow-ups
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FollowUp::query()->with(['customer', 'team', 'assignedEmployee', 'order']);

        $this->followUps->scopeForActor($query, $request->user('sanctum'));

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
        }
        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('follow_up_type')) {
            $query->where('follow_up_type', $request->input('follow_up_type'));
        }

        return FollowUpResource::collection($query->orderByDesc('id')->get());
    }

    /**
     * POST /follow-ups
     */
    public function store(StoreFollowUpRequest $request): JsonResponse
    {
        $followUp = $this->followUps->store($request->user('sanctum'), $request->validated());

        return (new FollowUpResource($followUp->loadMissing(['customer', 'team', 'assignedEmployee', 'order'])))->response()->setStatusCode(201);
    }

    /**
     * GET /follow-ups/{followUp}
     */
    public function show(Request $request, int $followUp): JsonResource
    {
        $model = FollowUp::with(['customer', 'team', 'assignedEmployee', 'order', 'history'])->findOrFail($followUp);
        $this->followUps->assertCanView($model, $request->user('sanctum'));

        return new FollowUpResource($model);
    }

    /**
     * GET /follow-ups/{followUp}/history
     */
    public function history(Request $request, int $followUp): JsonResponse
    {
        $model = FollowUp::with('history')->findOrFail($followUp);
        $this->followUps->assertCanView($model, $request->user('sanctum'));

        return response()->json([
            'follow_up' => new FollowUpResource($model),
            'history' => [
                'data' => FollowUpHistoryResource::collection($model->history()->latest('created_at')->get()),
            ],
        ]);
    }

    /**
     * POST /follow-ups/{followUp}/complete
     */
    public function complete(UpdateFollowUpRequest $request, int $followUp): JsonResource
    {
        $model = FollowUp::findOrFail($followUp);
        $this->followUps->complete($model, $request->user('sanctum'), $request->validated('outcome'), $request->validated('notes'));

        return new FollowUpResource($model->fresh());
    }

    /**
     * POST /follow-ups/{followUp}/reschedule
     */
    public function reschedule(UpdateFollowUpRequest $request, int $followUp): JsonResource
    {
        $model = FollowUp::findOrFail($followUp);

        $scheduledAt = $request->validated('scheduled_at');
        if (blank($scheduledAt)) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'A new scheduled_at is required to reschedule.',
            ]);
        }

        $this->followUps->reschedule(
            $model,
            $request->user('sanctum'),
            $scheduledAt,
            $request->validated('next_follow_up_at'),
            $request->validated('notes')
        );

        return new FollowUpResource($model->fresh());
    }

    /**
     * POST /follow-ups/{followUp}/cancel
     */
    public function cancel(UpdateFollowUpRequest $request, int $followUp): JsonResource
    {
        $model = FollowUp::findOrFail($followUp);
        $this->followUps->cancel($model, $request->user('sanctum'), $request->validated('notes'));

        return new FollowUpResource($model->fresh());
    }
}
