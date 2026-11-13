<?php

namespace App\Contracts;

use Illuminate\Http\Request;

/**
 * A gateway webhook driver must verify the incoming signature server-side
 * and normalise the event. Verification failure = the event is rejected
 * (400/403), never processed.
 */
interface GatewayWebhookVerifier
{
    /**
     * @return array{
     *     event_id: string,
     *     event_type: string,
     *     order_reference: ?string,
     *     transaction_reference: ?string,
     *     amount: string,
     *     currency: string,
     *     status: string
     * }
     *
     * @throws \App\Exceptions\GatewaySignatureException when the signature
     *        does not validate or the payload is malformed.
     */
    public function verify(Request $request): array;

    /**
     * Map a gateway event type onto a LeadX payment status
     * (paid | partially_paid | failed | refunded | pending).
     */
    public function paymentStatusFor(string $eventType, string $gatewayStatus): string;
}
