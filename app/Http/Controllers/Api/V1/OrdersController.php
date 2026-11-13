<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\WhatsappShareLogRequest;
use App\Http\Resources\OrderResource;
use App\Models\Admin;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderService;
use App\Services\WhatsappShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

class OrdersController extends Controller
{
    public function __construct(protected OrderService $orders, protected WhatsappShareService $whatsapp)
    {
    }

    /**
     * Resolve the team an order should be created under, honouring the
     * actor's role and scoping it to their own team where required.
     */
    protected function resolveTeamForCreation(Request $request): Team
    {
        $actor = $request->user('sanctum');

        if ($actor instanceof Team) {
            // A team token can only ever create orders for its own team.
            $requestedTeamId = $request->integer('team_id');

            if ($requestedTeamId > 0 && (int) $requestedTeamId !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'team_id' => 'A team may only create orders for itself.',
                ]);
            }

            return $actor;
        }

        if ($actor instanceof User) {
            if ($actor->team_id === null) {
                throw ValidationException::withMessages([
                    'team_id' => 'You are not assigned to a team and cannot create orders.',
                ]);
            }

            $team = Team::find($actor->team_id);

            // An employee may not create orders under a different team.
            $requestedTeamId = $request->integer('team_id');

            if ($requestedTeamId > 0 && (int) $requestedTeamId !== (int) $actor->team_id) {
                throw ValidationException::withMessages([
                    'team_id' => 'You may only create orders under your assigned team.',
                ]);
            }

            return $team;
        }

        // Admin: an explicit team_id is required (validated by the request).
        return Team::findOrFail($request->integer('team_id'));
    }

    /**
     * POST /orders (admin / team / employee scoped)
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $actor = $request->user('sanctum');
        $team = $this->resolveTeamForCreation($request);
        $validated = $request->validated();

        $employee = match (true) {
            $actor instanceof User => $actor,
            ! empty($validated['assigned_employee_id']) => User::find($validated['assigned_employee_id']),
            default => null,
        };

        $order = $this->orders->createForTeam($team, $employee, $validated);

        return (new OrderResource($order->loadMissing(['team', 'subscription'])))->response()->setStatusCode(201);
    }

    /**
     * GET /orders (scoped by actor role)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SalesOrder::query()->with(['team', 'subscription']);

        $actor = $request->user('sanctum');

        if ($actor instanceof Team) {
            $query->forTeam($actor->id);
        } elseif ($actor instanceof User) {
            // Employees see orders in their team (or they own).
            $query->where(function ($q) use ($actor): void {
                $q->where('assigned_employee_id', $actor->id)
                    ->orWhere('team_id', $actor->team_id);
            });
        }

        if ($request->filled('team_id')) {
            $query->where('team_id', $request->integer('team_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('assigned_employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('order_number')) {
            $query->where('order_number', 'like', '%'.str_replace('%', '', (string) $request->query('order_number')).'%');
        }

        return OrderResource::collection($query->orderByDesc('id')->get());
    }

    /**
     * GET /orders/{order}
     */
    public function show(Request $request, int $order): JsonResource
    {
        $thisOrder = SalesOrder::with(['team', 'subscription', 'assignedEmployee'])->findOrFail($order);
        $this->orders->assertCanAccess($thisOrder, $request->user('sanctum'));

        return new OrderResource($thisOrder);
    }

    /**
     * GET /orders/by-number/{orderNumber}
     */
    public function byNumber(Request $request, string $orderNumber): JsonResource
    {
        $thisOrder = SalesOrder::where('order_number', $orderNumber)->with(['team', 'subscription'])->firstOrFail();
        $this->orders->assertCanAccess($thisOrder, $request->user('sanctum'));

        return new OrderResource($thisOrder);
    }

    /**
     * GET /orders/{order}/whatsapp-share
     *
     * Returns eligibility plus a prefilled click-to-chat URL. The server
     * never sends the message itself.
     */
    public function whatsappShare(Request $request, int $order): JsonResponse
    {
        $thisOrder = SalesOrder::with(['team', 'subscription'])->findOrFail($order);
        $this->orders->assertCanAccess($thisOrder, $request->user('sanctum'));

        $payload = $this->whatsapp->prepare($thisOrder);

        return response()->json([
            'order_id' => $thisOrder->id,
            'order_number' => $thisOrder->order_number,
            'eligible' => $payload['eligible'],
            'reason' => $payload['reason'],
            'phone' => $payload['phone'],
            'message' => $payload['message'],
            'url' => $payload['url'],
        ]);
    }

    /**
     * POST /orders/{order}/whatsapp-share-log
     *
     * Records that a share was explicitly prepared/logged by the actor.
     * An employee-supplied `message` is accepted so they can review/edit
     * before sending, but it is scrubbed for secrets.
     */
    public function whatsappShareLog(WhatsappShareLogRequest $request, int $order): JsonResponse
    {
        $thisOrder = SalesOrder::with(['team', 'subscription'])->findOrFail($order);
        $this->orders->assertCanAccess($thisOrder, $request->user('sanctum'));

        $log = $this->whatsapp->logShare($thisOrder, $request->user('sanctum'), $request->validated('message'));

        return response()->json([
            'id' => $log->id,
            'status' => $log->status,
            'message' => 'Share recorded. The server does not send messages; open the generated link to send from your device.',
        ], 201);
    }

    /**
     * Activate an order as a paid subscription only when payment is verified.
     *
     * PUT /orders/{order}/activate
     */
    public function activate(Request $request, int $order): JsonResponse
    {
        $thisOrder = SalesOrder::findOrFail($order);
        $this->orders->assertCanAccess($thisOrder, $request->user('sanctum'));

        if ($thisOrder->payment_status !== 'paid') {
            throw ValidationException::withMessages([
                'payment_status' => 'An order may only become an active subscription once payment is verified.',
            ]);
        }

        $subscription = Subscription::updateOrCreate(
            ['order_id' => $thisOrder->id],
            [
                'team_id' => $thisOrder->team_id,
                'status' => 'active',
                'started_at' => now(),
                'expires_at' => SalesOrder::expiryFor((string) ($thisOrder->duration ?: '1y')),
            ]
        );

        $thisOrder->forceFill(['order_status' => 'active'])->save();

        return (new OrderResource($thisOrder->load('subscription', 'team')))
            ->response();
    }
}
