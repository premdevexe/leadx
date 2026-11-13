<?php

namespace App\Gateways;

use App\Contracts\GatewayWebhookVerifier;

/**
 * Shared plumbing for gateway drivers: signature verification over the
 * raw request body using the configured secret, plus the event
 * normalisation contract. Concrete drivers only implement
 * `verifySignature` and `parseEvent`.
 */
abstract class AbstractGateway implements GatewayWebhookVerifier
{
    public function __construct(protected string $secret)
    {
    }

    /**
     * The gateway name used to namespace the webhook ledger.
     */
    abstract public function name(): string;

    /**
     * Verify the raw request signature. Must throw
     * \App\Exceptions\GatewaySignatureException on any mismatch.
     */
    abstract protected function verifySignature(string $rawBody, ?string $signatureHeader): void;

    /**
     * Parse the (already signature-verified) body into the normal event.
     *
     * @return array<string, mixed>
     */
    abstract protected function parseEvent(string $rawBody): array;

    public function verify(\Illuminate\Http\Request $request): array
    {
        $rawBody = (string) $request->getContent();

        if ($rawBody === '') {
            throw new \App\Exceptions\GatewaySignatureException('Empty webhook payload.');
        }

        $this->verifySignature($rawBody, $request->header('X-Signature'));

        return $this->parseEvent($rawBody);
    }

    public function paymentStatusFor(string $eventType, string $gatewayStatus): string
    {
        return match (strtolower($eventType.':'.$gatewayStatus)) {
            'paid', 'completed', 'captured', 'succeeded', 'success' => 'paid',
            'partial', 'partially_paid' => 'partially_paid',
            'failed', 'failure', 'declined' => 'failed',
            'refunded', 'refund' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * HMAC-SHA256 signature check (constant-time compare). Used by the
     * mock driver; real gateways may override with their own scheme.
     */
    protected function assertHmac(string $rawBody, ?string $signature): void
    {
        if ($signature === null || $this->secret === '') {
            throw new \App\Exceptions\GatewaySignatureException('Missing webhook signature or secret.');
        }

        $expected = hash_hmac('sha256', $rawBody, $this->secret);

        if (! hash_equals($expected, $signature)) {
            throw new \App\Exceptions\GatewaySignatureException('Invalid webhook signature.');
        }
    }
}
