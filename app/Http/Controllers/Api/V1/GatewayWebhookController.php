<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\GatewaySignatureException;
use App\Http\Controllers\Controller;
use App\Services\GatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gateway webhook receiver.
 *
 * Unauthenticated by design — trust comes from the gateway's signed
 * payload, verified server-side by the configured driver
 * (config leadx.gateway.driver). A bad signature is rejected with 401
 * and never processed; a valid-but-duplicate event is acknowledged
 * idempotently with 200.
 */
class GatewayWebhookController extends Controller
{
    public function __construct(protected GatewayService $gateway)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            $result = $this->gateway->handle($request);
        } catch (GatewaySignatureException) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $event = $result['event'];

        // New events get 201; duplicates are acknowledged with 200 so the
        // gateway stops resending them.
        $code = $result['duplicate'] ? 200 : 201;

        return response()->json([
            'message' => 'Event received.',
            'event_id' => $event->event_id,
            'duplicate' => $result['duplicate'],
            'status' => $event->status,
        ], $code);
    }
}
