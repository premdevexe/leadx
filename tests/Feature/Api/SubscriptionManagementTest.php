<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\BillingRules;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    // Expiry date calculation is correct per billing cycle (unit-level).
    public function test_expiry_date_calculation_is_correct(): void
    {
        $rules = app(BillingRules::class);
        $from = \Illuminate\Support\Carbon::parse('2026-01-15 10:00:00');

        $order = SalesOrder::factory()->make(['duration' => '3m']);
        $quarterly = Plan::factory()->make(['billing_cycle' => 'quarterly']);
        $this->assertEquals(
            $from->copy()->addMonthsNoOverflow(3),
            $rules->expiry($order, $quarterly, $from)
        );

        $yearly = Plan::factory()->make(['billing_cycle' => 'yearly']);
        $this->assertEquals(
            $from->copy()->addMonthsNoOverflow(12),
            $rules->expiry($order, $yearly, $from)
        );

        // Day-accurate durations stay day-accurate under the monthly cycle.
        $short = SalesOrder::factory()->make(['duration' => '7d']);
        $monthly = Plan::factory()->make(['billing_cycle' => 'monthly']);
        $this->assertEquals(
            $from->copy()->addDays(7),
            $rules->expiry($short, $monthly, $from)
        );

        // No plan: the order's own duration drives the cycle.
        $legacy = SalesOrder::factory()->make(['duration' => '1y']);
        $this->assertEquals(
            $from->copy()->addMonthsNoOverflow(12),
            $rules->expiry($legacy, null, $from)
        );
    }

    // Renewal requires new verified payment.
    public function test_renewal_requires_new_verified_payment(): void
    {
        $team = Team::factory()->create(['team_code' => 'REN']);
        $admin = Admin::factory()->create();
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'closing_value' => '100.00',
            'order_status' => 'active',
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => '100.00',
        ]);

        app(PaymentService::class)->verify($payment, $admin->id);
        $subscription = $order->fresh()->subscription;
        $this->assertSame('active', $subscription->status);

        // No *new* verified money → renewal is refused (idempotent).
        $this->postJson(
            "/api/v1/subscriptions/{$subscription->id}/renew",
            [],
            $this->actingAsToken($this->teamToken($team))
        )->assertStatus(422);

        $this->assertSame('active', $subscription->fresh()->status);
    }

    // Cancel closes the subscription and its order.
    public function test_cancel_subscription_closes_order(): void
    {
        $team = Team::factory()->create(['team_code' => 'CAN']);
        $admin = Admin::factory()->create();
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'closing_value' => '100.00',
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => '100.00',
        ]);
        app(PaymentService::class)->verify($payment, $admin->id);
        $subscription = $order->fresh()->subscription;

        $this->postJson(
            "/api/v1/subscriptions/{$subscription->id}/cancel",
            [],
            $this->actingAsToken($this->teamToken($team))
        )->assertOk();

        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->order_status);
    }

    // Team data isolation: teams never see each other's subscriptions.
    public function test_team_subscription_isolation(): void
    {
        $teamA = Team::factory()->create(['team_code' => 'ISO']);
        $teamB = Team::factory()->create(['team_code' => 'ISM']);

        $admin = Admin::factory()->create();
        $orderA = SalesOrder::factory()->create(['team_id' => $teamA->id, 'closing_value' => '100.00']);
        Payment::factory()->create(['order_id' => $orderA->id, 'amount' => '100.00']);
        app(PaymentService::class)->verify($orderA->payments()->first(), $admin->id);
        $subscriptionA = $orderA->fresh()->subscription;

        $tokenB = $this->actingAsToken($this->teamToken($teamB));

        // Team B's index shows nothing.
        $this->getJson('/api/v1/subscriptions', $tokenB)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Team B cannot read team A's subscription.
        $this->getJson("/api/v1/subscriptions/{$subscriptionA->id}", $tokenB)
            ->assertStatus(422);

        // Team B cannot cancel team A's subscription.
        $this->postJson("/api/v1/subscriptions/{$subscriptionA->id}/cancel", [], $tokenB)
            ->assertStatus(422);
    }

    // An employee may only see their own team's subscriptions.
    public function test_employee_subscription_isolation(): void
    {
        $teamA = Team::factory()->create(['team_code' => 'EMP']);
        $teamB = Team::factory()->create(['team_code' => 'EMQ']);
        $admin = Admin::factory()->create();

        $orderA = SalesOrder::factory()->create(['team_id' => $teamA->id, 'closing_value' => '100.00']);
        Payment::factory()->create(['order_id' => $orderA->id, 'amount' => '100.00']);
        app(PaymentService::class)->verify($orderA->payments()->first(), $admin->id);

        $employeeB = User::factory()->create(['team_id' => $teamB->id]);

        $this->getJson('/api/v1/subscriptions', $this->actingAsToken($this->employeeToken($employeeB)))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
