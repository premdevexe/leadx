<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SubscriptionActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationAndRefundTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;
    protected User $employee;
    protected Admin $admin;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create([
            'team_name' => 'Operations Team',
            'team_code' => 'OPS',
        ]);

        $this->employee = User::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $this->admin = Admin::factory()->create([
            'email' => 'admin_refunds@leadx.com',
            'password' => bcrypt('adminpass'),
        ]);

        $this->product = Product::factory()->create([
            'status' => 'active',
            'price' => 1200.00,
            'minimum_closing_price' => 1000.00,
            'api_key_required' => false,
        ]);
    }

    public function test_admin_can_cancel_order_with_mandatory_reason_and_suspends_subscription(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 1000.00,
            'order_status' => 'active',
        ]);

        $subscription = Subscription::factory()->create([
            'order_id' => $order->id,
            'team_id' => $this->team->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/orders/{$order->id}/cancel", [
                'cancellation_reason' => 'Customer requested total cancellation due to change of plans.',
            ]);

        $response->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('cancelled', $order->order_status);
        $this->assertEquals('Customer requested total cancellation due to change of plans.', $order->cancellation_reason);
        $this->assertEquals($this->admin->id, $order->cancelled_by);
        $this->assertNotNull($order->cancelled_at);

        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);

        // Employee notified
        $this->assertTrue(
            $this->employee->notifications()->where('data->action', 'cancelled')->exists()
        );
    }

    public function test_admin_cannot_cancel_order_without_mandatory_reason(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'order_status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/orders/{$order->id}/cancel", [
                'cancellation_reason' => '',
            ]);

        $response->assertSessionHasErrors('cancellation_reason');
        $this->assertEquals('active', $order->fresh()->order_status);
    }

    public function test_admin_can_process_full_refund_and_updates_status(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 1000.00,
            'payment_status' => 'paid',
            'order_status' => 'active',
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 1000.00,
            'payment_status' => 'paid',
            'verified_at' => now(),
            'verified_by_admin_id' => $this->admin->id,
        ]);

        $subscription = Subscription::factory()->create([
            'order_id' => $order->id,
            'team_id' => $this->team->id,
            'status' => 'active',
        ]);

        $this->assertEquals(1000.00, $order->remainingRefundable());

        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/orders/{$order->id}/refunds", [
                'amount' => 1000.00,
                'type' => 'full',
                'reason' => 'Customer dissatisfied within 30-day money-back guarantee.',
            ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('refunds', [
            'order_id' => $order->id,
            'admin_id' => $this->admin->id,
            'amount' => 1000.00,
            'type' => 'full',
            'reason' => 'Customer dissatisfied within 30-day money-back guarantee.',
        ]);

        $order->refresh();
        $this->assertEquals('refunded', $order->payment_status);
        $this->assertEquals(0.00, $order->remainingRefundable());

        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);

        // Employee received refund notification
        $this->assertTrue(
            $this->employee->notifications()->where('data->action', 'refund')->exists()
        );
    }

    public function test_admin_can_process_partial_refund(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 1000.00,
            'payment_status' => 'paid',
            'order_status' => 'active',
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 1000.00,
            'payment_status' => 'paid',
            'verified_at' => now(),
            'verified_by_admin_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/orders/{$order->id}/refunds", [
                'amount' => 300.00,
                'type' => 'partial',
                'reason' => 'Partial goodwill credit for service disruption.',
            ]);

        $response->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('partially_refunded', $order->payment_status);
        $this->assertEquals(300.00, $order->totalRefunded());
        $this->assertEquals(700.00, $order->remainingRefundable());
    }

    public function test_admin_cannot_refund_more_than_remaining_refundable_amount(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 500.00,
            'payment_status' => 'paid',
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 500.00,
            'payment_status' => 'paid',
            'verified_at' => now(),
            'verified_by_admin_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/orders/{$order->id}/refunds", [
                'amount' => 600.00,
                'reason' => 'Attempting over-refund.',
            ]);

        $response->assertSessionHasErrors('amount');
        $this->assertEquals(0, $order->refunds()->count());
    }

    public function test_admin_cannot_refund_unpaid_order(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 500.00,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/orders/{$order->id}/refunds", [
                'amount' => 100.00,
                'reason' => 'Attempting refund with 0 balance.',
            ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, $order->refunds()->count());
    }

    public function test_failed_refund_does_not_reduce_remaining_refundable_or_change_status(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 1000.00,
            'payment_status' => 'paid',
            'order_status' => 'active',
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 1000.00,
            'payment_status' => 'paid',
            'verified_at' => now(),
            'verified_by_admin_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/orders/{$order->id}/refunds", [
                'amount' => 300.00,
                'status' => 'failed',
                'reason' => 'Gateway rejected refund: card expired.',
            ]);

        $response->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals(0.00, $order->totalRefunded());
        $this->assertEquals(1000.00, $order->remainingRefundable());
    }
}
