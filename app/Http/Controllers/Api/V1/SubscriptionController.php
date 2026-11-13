<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Admin;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Subscription access for admin / team / employee, plus renewal and
 * cancellation. Read access is scoped per actor; a team may never see
 * another team's subscriptions and an employee may only see their own
 * team's (or orders they personally own).
 */
class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptions)
    {
    }

    /**
     * GET /api/v1/subscriptions
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user('sanctum');
        $query = $this->subscriptions->visibleTo($actor);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('team_id') && $actor instanceof Admin) {
            $query->where('team_id', $request->integer('team_id'));
        }

        return SubscriptionResource::collection($query->latest()->get());
    }

    /**
     * GET /api/v1/subscriptions/{subscription}
     */
    public function show(Request $request, int $id): JsonResource
    {
        $actor = $request->user('sanctum');

        $query = $this->subscriptions->visibleTo($actor)->whereKey($id);

        if (! $query->exists()) {
            throw ValidationException::withMessages([
                'subscription' => 'Subscription not found or not accessible.',
            ]);
        }

        $subscription = $query->first();

        return (new SubscriptionResource($subscription->load('order.team', 'order.assignedEmployee', 'customer', 'plan')))->response();
    }

    /**
     * POST /api/v1/subscriptions/{subscription}/renew
     *
     * Requires verified payment on the underlying order covering the full
     * amount due; extends the expiry/renewal dates accordingly.
     */
    public function renew(Request $request, int $id): JsonResponse
    {
        $actor = $request->user('sanctum');
        $subscription = $this->resolveAccessible($request, $id);

        $renewed = $this->subscriptions->renew($subscription);

        $this->recordAccess($request, 'subscription.renewed', $renewed);

        return (new SubscriptionResource($renewed->load('order.team')))->response();
    }

    /**
     * POST /api/v1/subscriptions/{subscription}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $subscription = $this->resolveAccessible($request, $id);

        $cancelled = $this->subscriptions->cancel($subscription);

        $this->recordAccess($request, 'subscription.cancelled', $cancelled);

        return (new SubscriptionResource($cancelled->load('order.team')))->response();
    }

    protected function resolveAccessible(Request $request, int $id): Subscription
    {
        $actor = $request->user('sanctum');

        $subscription = $this->subscriptions->visibleTo($actor)->whereKey($id)->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => 'Subscription not found or not accessible.',
            ]);
        }

        // Renew/cancel additionally require team ownership or admin — an
        // employee can see their team's subscriptions but only a team
        // account or admin may mutate them.
        if (! $actor instanceof Admin) {
            if (($actor instanceof Team && (int) $actor->id !== (int) $subscription->team_id)
                || ($actor instanceof User && (int) ($actor->team_id ?? 0) !== (int) $subscription->team_id)) {
                throw ValidationException::withMessages([
                    'subscription' => 'You may not modify subscriptions outside your team.',
                ]);
            }
        }

        return $subscription;
    }

    protected function recordAccess(Request $request, string $event, Subscription $subscription): void
    {
        DB::table('audit_logs')->insert([
            'auditable_type' => $subscription->getMorphClass(),
            'auditable_id' => $subscription->id,
            'event' => $event,
            'new_values' => json_encode(['actor_type' => $request->user('sanctum')::class, 'at' => now()->toIso8601String()]),
            'ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
