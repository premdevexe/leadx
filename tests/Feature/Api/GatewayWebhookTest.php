<?php

namespace Tests\Feature\Api;

use App\Gateways\MockGateway;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GatewayWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;
    protected string $secret = 'test-gateway-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['leadx.gateway.secret' => $this->secret]);
        $this->team = Team::factory()->create(['team_code' => 'GWY']);
    }

    protected function paidOrder(string $value = '100.00'): SalesOrder
    {
        return SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'product_value' => $value,
            'closing_value' => $value,
        ]);
    }

    /**
     * Post a raw signed payload to the webhook. A null signature means
     * "sign it correctly with the shared secret"; a provided signature is
     * used verbatim (for bad/missing-signature cases).
     */
    protected function postWebhook(array $payload, ?string $signature = null): TestResponse
    {
        if ($signature === null) {
            $signed = MockGateway::signedPayload($payload, $this->secret);

            return $this->call(
                'POST',
                '/api/v1/gateway/webhook',
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'X_SIGNATURE' => $signed['signature']],
                $signed['body']
            );
        }

        return $this->call(
            'POST',
            '/api/v1/gateway/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'X_SIGNATURE' => $signature],
            json_encode($payload)
        );
    }

    // 1. A valid signed payment-completed webhook activates the order.
    public function test_signed_webhook_activates_subscription(): void
    {
        $order = $this->paidOrder('150.00');

        $this->postWebhook([
            'event_id' => 'evt_001',
            'event_type' => 'payment.completed',
            'order_reference' => $order->order_number,
            'transaction_reference' => 'TXN-1',
            'amount' => '150.00',
        ])->assertStatus(201);

        $this->assertSame('active', $order->fresh()->subscription->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'source' => 'gateway',
            'transaction_reference' => 'TXN-1',
            'verification_source' => 'gateway',
        ]);
    }

    // 2. A duplicate webhook does not duplicate the subscription.
    public function test_duplicate_webhook_does_not_duplicate_subscription(): void
    {
        $order = $this->paidOrder('100.00');

        $payload = [
            'event_id' => 'evt_dup',
            'event_type' => 'payment.completed',
            'order_reference' => $order->order_number,
            'transaction_reference' => 'TXN-2',
            'amount' => '100.00',
        ];

        $this->postWebhook($payload)->assertStatus(201);
        $this->assertSame(1, Subscription::where('order_id', $order->id)->count());
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());

        // Resend the identical event.
        $this->postWebhook($payload)->assertStatus(200)->assertJsonPath('duplicate', true);
        $this->assertSame(1, Subscription::where('order_id', $order->id)->count());
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        $this->assertSame(1, WebhookEvent::where('event_id', 'evt_dup')->count());
    }

    // 3. A bad signature is rejected and never processed.
    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->paidOrder('100.00');

        $this->postWebhook([
            'event_id' => 'evt_bad',
            'event_type' => 'payment.completed',
            'order_reference' => $order->order_number,
            'amount' => '100.00',
        ], 'wrong-signature')->assertStatus(401);

        $this->assertNull($order->fresh()->subscription);
        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
    }

    // 4. A missing signature is rejected.
    public function test_missing_signature_is_rejected(): void
    {
        $order = $this->paidOrder('100.00');

        $this->postWebhook([
            'event_id' => 'evt_none',
            'event_type' => 'payment.completed',
        ], '')->assertStatus(401);

        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
    }

    // 5. A failed-payment webhook records a failed payment and does not activate.
    public function test_failed_gateway_event_does_not_activate(): void
    {
        $order = $this->paidOrder('100.00');

        $this->postWebhook([
            'event_id' => 'evt_fail',
            'event_type' => 'payment.failed',
            'order_reference' => $order->order_number,
            'amount' => '100.00',
        ])->assertStatus(201);

        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->subscription);
    }
}
