<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Employee subscriptions — mirrors the web panel's Subscriptions page and
 * its Expiry Reminders view. Read-only for the individual employee: renew
 * and cancel are reserved for the team account / admin (enforced by the
 * shared SubscriptionController), so this controller exposes only reads.
 */
class SubscriptionsController extends Controller
{
    use EmployeeScoped;

    /**
     * GET /api/v1/employee/subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $resolved = $this->resolveView($employee, $request->input('view'));
        $query = $this->scopedSubscriptions($employee, $resolved['view'], $resolved['grant']);

        if ($request->filled('status') && in_array($request->input('status'), \App\Models\Subscription::STATUSES, true)) {
            $query->where('status', $request->input('status'));
        }

        $subscriptions = $query
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return response()->json([
            'meta' => $this->listMeta($resolved, $subscriptions),
            'data' => SubscriptionResource::collection($subscriptions->items())->resolve(),
        ]);
    }

    /**
     * GET /api/v1/employee/subscriptions/expiring
     */
    public function expiring(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $resolved = $this->resolveView($employee, $request->input('view'));
        $subscriptions = $this->scopedSubscriptions($employee, $resolved['view'], $resolved['grant'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(30))
            ->where('expires_at', '>', now())
            ->orderBy('expires_at')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 100));

        return response()->json([
            'meta' => $this->listMeta($resolved, $subscriptions),
            'data' => SubscriptionResource::collection($subscriptions->items())->resolve(),
        ]);
    }

    /**
     * GET /api/v1/employee/subscriptions/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $resolved = $this->resolveView($employee, $request->input('view'));
        $subscription = $this->scopedSubscriptions($employee, $resolved['view'], $resolved['grant'])
            ->whereKey($id)
            ->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => 'Subscription not found or not accessible.',
            ]);
        }

        return response()->json([
            'data' => (new SubscriptionResource(
                $subscription->load('order.team', 'order.assignedEmployee', 'customer', 'plan')
            )),
        ]);
    }
}
