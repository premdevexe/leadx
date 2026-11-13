<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Services\PaymentService;
use App\Services\SalesAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;
    protected Admin $admin;
    protected SalesAnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create(['team_code' => 'ANA']);
        $this->admin = Admin::factory()->create();
        $this->analytics = app(SalesAnalyticsService::class);
    }

    protected function order(string $productValue, string $closing, ?string $verifiedAmount = null): SalesOrder
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'product_value' => $productValue,
            'closing_value' => $closing,
            'payment_status' => 'pending',
        ]);

        if ($verifiedAmount !== null) {
            $payment = Payment::factory()->create([
                'order_id' => $order->id,
                'amount' => $verifiedAmount,
            ]);

            app(PaymentService::class)->verify($payment, $this->admin->id);
        }

        return $order;
    }

    // Revenue reports must exclude pending / failed / uncollected payments.
    public function test_revenue_reports_exclude_uncollected_payments(): void
    {
        // Fully verified.
        $this->order('100.00', '100.00', '100.00');
        // Verified only half — the uncollected half must not count.
        $this->order('200.00', '200.00', '100.00');
        // Pending payment: never counted.
        $this->order('300.00', '300.00', null);

        $summary = $this->analytics->summary($this->team);

        // Total product value sums all three.
        $this->assertSame('600.00', $summary['total_product_value']);
        // Deal closing value also sums all three.
        $this->assertSame('600.00', $summary['total_deal_closing_value']);
        // Collected = only the verified amounts: 100 + 100.
        $this->assertSame('200.00', $summary['amount_received']);
        // Pending collection = closing not yet collected: 600 − 200.
        $this->assertSame('400.00', $summary['pending_collection']);
    }

    // Failed payments count as zero.
    public function test_failed_payments_are_not_counted_as_revenue(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'product_value' => '100.00',
            'closing_value' => '100.00',
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => '100.00',
            'payment_status' => 'failed',
            'verified_by_admin_id' => $this->admin->id,
            'verification_source' => 'admin',
            'verified_at' => now(),
        ]);

        $summary = $this->analytics->summary($this->team);

        $this->assertSame('0.00', $summary['amount_received']);
        $this->assertSame('100.00', $summary['pending_collection']);
    }

    // All money math is exact DECIMAL(15,2) — no float drift.
    public function test_money_math_is_exact_decimal(): void
    {
        // 0.1 + 0.2 must be exactly 0.30 (the classic float trap).
        $this->assertSame('0.30', \App\Support\Money::sum(['0.10', '0.20']));

        $this->assertSame('100.05', \App\Support\Money::sum(['0.10', '0.20', '99.75']));

        // Percents stay exact too.
        $this->assertSame('50.00', \App\Support\Money::percentOf('25.00', '50.00'));
    }

    // Per-team figures only count that team's money.
    public function test_team_revenue_is_isolated(): void
    {
        $otherTeam = Team::factory()->create(['team_code' => 'ANT']);

        $this->order('100.00', '100.00', '100.00');

        $other = SalesOrder::factory()->create([
            'team_id' => $otherTeam->id,
            'product_value' => '500.00',
            'closing_value' => '500.00',
        ]);
        $p = Payment::factory()->create(['order_id' => $other->id, 'amount' => '500.00']);
        app(PaymentService::class)->verify($p, $this->admin->id);

        $this->assertSame('100.00', $this->analytics->summary($this->team)['amount_received']);
        $this->assertSame('500.00', $this->analytics->summary($otherTeam)['amount_received']);
    }
}
