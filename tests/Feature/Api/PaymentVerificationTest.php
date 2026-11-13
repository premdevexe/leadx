<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SubscriptionActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected Admin $admin;
    protected Team $team;
    protected PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->team = Team::factory()->create(['team_code' => 'PAY']);
        $this->payments = app(PaymentService::class);
    }

    protected function orderFor(string $closing = '100.00'): SalesOrder
    {
        return SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'product_value' => $closing,
            'closing_value' => $closing,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ]);
    }

    protected function pendingPayment(SalesOrder $order, string $amount = '100.00'): Payment
    {
        return Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => $amount,
            'payment_status' => 'pending',
            'source' => 'manual',
            'verified_by_admin_id' => null,
            'verification_source' => null,
            'verified_at' => null,
        ]);
    }

    // 1. A pending (unverified) payment must never activate a subscription.
    public function test_pending_payment_does_not_activate_subscription(): void
    {
        $order = $this->orderFor();
        $this->pendingPayment($order);

        // No verification has happened: the activator must leave it pending.
        $this->actingAsToken($this->adminToken($this->admin));

        $activator = app(SubscriptionActivator::class);
        $result = $activator->maybeActivate($order->fresh());

        $this->assertNull($result);

        $subscription = Subscription::where('order_id', $order->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame('pending', $subscription->status);
        $this->assertSame('pending', $order->fresh()->order_status);
    }

    // 2. A verified, sufficient payment activates the subscription.
    public function test_verified_payment_activates_subscription(): void
    {
        $order = $this->orderFor('100.00');
        $payment = $this->pendingPayment($order, '100.00');

        $updated = $this->payments->verify($payment, $this->admin->id, 'bank transfer confirmed');

        $this->assertSame('paid', $updated->payment_status);
        $this->assertNotNull($updated->verified_by_admin_id);
        $this->assertNotNull($updated->verified_at);
        $this->assertTrue($updated->isVerified());

        $subscription = $order->fresh()->subscription;
        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->started_at);
        $this->assertNotNull($subscription->expires_at);
        $this->assertSame('100.00', (string) $subscription->amount_paid);
        $this->assertSame('active', $order->fresh()->order_status);

        // A CRM customer was created for the verified purchase.
        $this->assertDatabaseHas('customers', [
            'team_id' => $this->team->id,
            'status' => 'paying',
        ]);
    }

    // 3. A failed payment can never activate a subscription.
    public function test_failed_payment_cannot_activate_subscription(): void
    {
        $order = $this->orderFor('100.00');
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => '100.00',
            'payment_status' => 'failed',
            'verified_by_admin_id' => null,
        ]);

        $activator = app(SubscriptionActivator::class);
        $this->assertNull($activator->maybeActivate($order->fresh()));

        // A placeholder row may exist, but it can never become active.
        $subscription = Subscription::where('order_id', $order->id)->first();

        if ($subscription) {
            $this->assertSame('pending', $subscription->status);
        }

        $this->assertNotSame('active', $order->fresh()->order_status);
    }

    // 4. Rejecting a previously verified payment suspends the activation.
    public function test_rejecting_a_verified_payment_suspends_subscription(): void
    {
        $order = $this->orderFor('100.00');
        $payment = $this->pendingPayment($order, '100.00');

        $this->payments->verify($payment, $this->admin->id);
        $this->assertSame('active', $order->fresh()->subscription->status);

        $rejected = $this->payments->reject($payment->fresh(), $this->admin->id, 'bounced');

        $this->assertSame('failed', $rejected->payment_status);
        $this->assertSame('suspended', $order->fresh()->subscription->status);
        $this->assertSame('suspended', $order->fresh()->order_status);
    }

    // 5. Partial payment is refused under the default (paid) policy…
    public function test_partial_payment_does_not_activate_under_paid_policy(): void
    {
        $order = $this->orderFor('100.00');
        $payment = $this->pendingPayment($order, '40.00');

        $this->payments->verify($payment, $this->admin->id);

        $this->assertSame('partially_paid', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->subscription->status);
        $this->assertNotSame('active', $order->fresh()->order_status);
    }

    // …but activates when the policy explicitly permits partial payments.
    public function test_partial_payment_activates_when_policy_permits(): void
    {
        config([
            'leadx.payments.activate_on' => 'partial',
            'leadx.payments.minimum_verified_percent' => 50.0,
        ]);

        $order = $this->orderFor('100.00');
        $payment = $this->pendingPayment($order, '50.00'); // exactly 50%

        $this->payments->verify($payment, $this->admin->id);

        $this->assertSame('active', $order->fresh()->subscription->status);
        $this->assertSame('50.00', (string) $order->fresh()->subscription->amount_paid);
    }

    // 6. Only an admin may verify — a team token gets a 403.
    public function test_unauthorized_users_cannot_verify_payments(): void
    {
        $order = $this->orderFor();
        $payment = $this->pendingPayment($order);

        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/verify', [])
            ->assertStatus(401);

        $teamToken = $this->actingAsToken($this->teamToken($this->team));
        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/verify', [], $teamToken)
            ->assertStatus(403);

        $employee = User::factory()->create(['team_id' => $this->team->id]);
        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/verify', [], $this->actingAsToken($this->employeeToken($employee)))
            ->assertStatus(403);
    }

    // 7. An admin can verify through the API and it activates.
    public function test_admin_can_verify_payment_via_api(): void
    {
        $order = $this->orderFor('200.00');
        $payment = $this->pendingPayment($order, '200.00');

        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/verify', [
            'notes' => 'UPI reference checked',
        ], $this->actingAsToken($this->adminToken($this->admin)))
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.verified', true);

        $this->assertSame('active', $order->fresh()->subscription->status);
    }

    // 8. The admin can list pending payments.
    public function test_admin_can_list_pending_payments(): void
    {
        $order = $this->orderFor();
        $this->pendingPayment($order);

        $this->getJson('/api/v1/admin/payments?status=pending', $this->actingAsToken($this->adminToken($this->admin)))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
