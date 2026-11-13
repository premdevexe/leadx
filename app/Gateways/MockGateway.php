<?php

namespace App\Gateways;

use App\Exceptions\GatewaySignatureException;

/**
 * Reference gateway driver. It verifies an HMAC-SHA256 signature over the
 * raw JSON body (secret from config leadx.gateway.secret) and parses a
 * small, stable event shape. A real gateway (Razorpay, Stripe, …) plugs
 * in here by extending AbstractGateway without touching any controller.
 */
class MockGateway extends AbstractGateway
{
    public function name(): string
    {
        return config('leadx.gateway.driver', 'mock');
    }

    protected function verifySignature(string $rawBody, ?string $signatureHeader): void
    {
        $this->assertHmac($rawBody, $signatureHeader);
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseEvent(string $rawBody): array
    {
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw new GatewaySignatureException('Malformed webhook payload.');
        }

        $event = [
            'event_id' => (string) ($payload['event_id'] ?? ''),
            'event_type' => (string) ($payload['event_type'] ?? 'payment.completed'),
            'order_reference' => $payload['order_reference'] ?? $payload['order_number'] ?? null,
            'transaction_reference' => $payload['transaction_reference'] ?? null,
            'amount' => isset($payload['amount']) ? (string) $payload['amount'] : null,
            'currency' => (string) ($payload['currency'] ?? 'USD'),
            'status' => (string) ($payload['status'] ?? $payload['event_type']),
        ];

        if ($event['event_id'] === '') {
            // Gateways that don't expose an event id fall back to the
            // transaction reference for idempotency.
            $event['event_id'] = $event['transaction_reference'] ?? 'unknown';
        }

        return $event;
    }

    /**
     * Build a signed payload for tests and local development.
     *
     * @param array<string, mixed> $payload
     */
    public static function signedPayload(array $payload, string $secret): array
    {
        $body = json_encode($payload);

        return [
            'body' => $body,
            'signature' => hash_hmac('sha256', $body, $secret),
        ];
    }
}
