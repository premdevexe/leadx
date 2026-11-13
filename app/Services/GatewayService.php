<?php

namespace App\Services;

use App\Contracts\GatewayWebhookVerifier;
use App\Exceptions\GatewaySignatureException;
use App\Models\SalesOrder;
use App\Models\WebhookEvent;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gateway webhook pipeline: signature check → idempotency check →
 * payment record → (activation via PaymentService).
 *
 * Idempotency is two-layered:
 *   1. The webhook ledger (webhook_events) keyed on the gateway's own
 *      event id — a resent event is recognised and skipped before it
 *      touches anything.
 *   2. The payment row itself is keyed on (order, source, reference),
 *      so even a fresh event for the same transaction can't mint a
 *      duplicate receipt.
 *
 * Subscriptions are created/updated only through
 * {@see SubscriptionActivator}, which is itself keyed on the unique
 * order_id — repeated events can never duplicate a subscription.
 */
class GatewayService
{
    public function __construct(protected PaymentService $payments)
    {
    }

    /**
     * Process one webhook delivery.
     *
     * @return array{event: WebhookEvent, duplicate: bool}
     *
     * @throws GatewaySignatureException|InvalidArgumentException
     */
    public function handle(Request $request): array
    {
        $verifier = $this->verifier();

        // 1. Signature: reject outright on any mismatch.
        try {
            $event = $verifier->verify($request);
        } catch (GatewaySignatureException $e) {
            // Unauthenticated webhook traffic is recorded as ignored and
            // re-thrown so the HTTP layer can return 401.
            $this->recordRejected($verifier, $request, $e->getMessage());

            throw $e;
        }

        $eventId = (string) $event['event_id'];
        $gateway = $verifier->name();

        // 2. Idempotency: an already-processed event is acknowledged,
        //    never reprocessed.
        $ledger = WebhookEvent::firstOrNew([
            'gateway' => $gateway,
            'event_id' => $eventId,
        ]);

        if ($ledger->exists && $ledger->status === 'processed') {
            return ['event' => $ledger, 'duplicate' => true];
        }

        $ledger = DB::transaction(function () use ($ledger, $gateway, $eventId, $event) {
            $order = $this->resolveOrder($event['order_reference'] ?? null, $event['event_type'] ?? '');

            if ($order) {
                $ledger->order_id = $order->id;
            }

            $ledger->gateway = $gateway;
            $ledger->event_id = $eventId;
            $ledger->event_type = $event['event_type'] ?? null;
            $ledger->payload = $event;
            $ledger->status = $order ? 'processed' : 'ignored';
            $ledger->processed_at = now();
            $ledger->save();

            if ($order) {
                $this->payments->recordGatewayPayment(
                    $order,
                    (string) ($event['event_type'] ?? 'payment.completed'),
                    $event['transaction_reference'] ?? null,
                    $event['amount'] ?? null,
                    $event['currency'] ?? null,
                    null,
                    $eventId,
                );
            }

            return $ledger;
        });

        return ['event' => $ledger, 'duplicate' => false];
    }

    /**
     * An order reference the gateway can't resolve is an unactionable
     * event (e.g. a refund for an order we never had); it is still
     * ledgered so the gateway stops resending it.
     */
    protected function resolveOrder(?string $reference, string $eventType): ?SalesOrder
    {
        if ($reference === null) {
            return null;
        }

        $byNumber = SalesOrder::where('order_number', $reference)->first();

        if ($byNumber) {
            return $byNumber;
        }

        return SalesOrder::whereKey($reference)->first();
    }

    protected function recordRejected(GatewayWebhookVerifier $verifier, Request $request, string $reason): void
    {
        try {
            WebhookEvent::create([
                'gateway' => $verifier->name(),
                'event_id' => 'rejected:'.substr(sha1((string) $request->getContent().$request->ip()), 0, 24),
                'event_type' => 'signature_failure',
                'status' => 'ignored',
                'payload' => ['reason' => $reason],
            ]);
        } catch (Throwable) {
            // Never let ledger bookkeeping break the 401 response (e.g. a
            // duplicate rejected event tripping the unique constraint).
        }
    }

    protected function verifier(): GatewayWebhookVerifier
    {
        $driver = config('leadx.gateway.driver', 'mock');

        if ($driver === 'mock') {
            return new \App\Gateways\MockGateway((string) config('leadx.gateway.secret', ''));
        }

        $class = '\\App\\Gateways\\'.ucfirst($driver).'Gateway';

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Unknown gateway driver: {$driver}.");
        }

        return new $class((string) config('leadx.gateway.secret', ''));
    }
}
