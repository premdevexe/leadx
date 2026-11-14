<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderApiKeyRequest;
use App\Models\SalesOrder;
use App\Services\OrderCredentialService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Order-scoped customer API key endpoints (admin / team / employee).
 *
 * Access is scoped per actor via {@see OrderService::assertCanAccess()} so a
 * team or employee can only ever reach their own order's credential, and
 * reading the plaintext (`reveal`) is additionally gated by
 * {@see OrderCredentialService::canManage()}. No response ever carries a
 * key except the explicit, authorized `reveal` action.
 */
class OrderCredentialController extends Controller
{
    public function __construct(protected OrderCredentialService $credentials, protected OrderService $orders)
    {
    }

    /**
     * GET /api/v1/orders/{order}/api-key — masked status + who/when.
     */
    public function show(Request $request, int $order): JsonResponse
    {
        $thisOrder = SalesOrder::findOrFail($order);
        $this->orders->assertCanAccess($thisOrder, $request->user('sanctum'));

        $status = $this->credentials->status($thisOrder);

        return response()->json([
            'order_id' => $thisOrder->id,
            'order_number' => $thisOrder->order_number,
            'api_key' => [
                'configured' => $status['configured'],
                'masked_key' => $status['masked_key'],
                'updated_by_type' => $status['updated_by_type'],
                'updated_at' => $status['updated_at'],
            ],
            // Can this actor reveal/replace the key? (team/employee scoping)
            'can_manage' => $this->credentials->canManage($thisOrder, $request->user('sanctum')),
        ]);
    }

    /**
     * POST /api/v1/orders/{order}/api-key — store or replace the key.
     */
    public function store(StoreOrderApiKeyRequest $request, int $order): JsonResponse
    {
        $thisOrder = SalesOrder::findOrFail($order);
        $actor = $request->user('sanctum');
        $this->orders->assertCanAccess($thisOrder, $actor);

        if (! $this->credentials->canManage($thisOrder, $actor)) {
            throw ValidationException::withMessages([
                'api_key' => 'You may not manage the API key for this order.',
            ]);
        }

        $result = $this->credentials->store($thisOrder, $request->validated('api_key'), $actor, $request->ip());

        if (! $result['saved']) {
            throw ValidationException::withMessages(['api_key' => $result['message']]);
        }

        return response()->json([
            'message' => $result['message'],
            'api_key' => $this->credentials->status($thisOrder),
        ], 201);
    }

    /**
     * POST /api/v1/orders/{order}/api-key/reveal — the one authorized
     * plaintext read. Returns the key; it is not logged by the app.
     */
    public function reveal(Request $request, int $order): JsonResponse
    {
        $thisOrder = SalesOrder::findOrFail($order);
        $actor = $request->user('sanctum');
        $this->orders->assertCanAccess($thisOrder, $actor);

        $key = $this->credentials->reveal($thisOrder, $actor);

        if ($key === null) {
            throw ValidationException::withMessages([
                'api_key' => 'No API key is stored for this order, or you do not have access to read it.',
            ]);
        }

        return response()->json([
            'order_id' => $thisOrder->id,
            'order_number' => $thisOrder->order_number,
            'api_key' => $key,
        ]);
    }
}
