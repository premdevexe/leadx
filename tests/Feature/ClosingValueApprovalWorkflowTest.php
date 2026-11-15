<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Notification;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosingValueApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_super_admin_can_accept_closing_value(): void
    {
        $admin = Admin::factory()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);
        $product = Product::factory()->create([
            'price' => 1000,
            'minimum_closing_price' => 800,
        ]);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'product_id' => $product->id,
            'product_value' => 1000,
            'minimum_closing_price_snapshot' => 800,
            'closing_value' => 700,
            'closing_value_status' => 'Requested',
            'closing_approval_requested_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.orders.closing-approval.accept', $order), [
            'notes' => 'Approved as volume exception',
        ]);

        $response->assertSessionHas('status');
        $order->refresh();

        $this->assertEquals('Accepted', $order->closing_value_status);
        $this->assertNotNull($order->closing_approval_actioned_at);
        $this->assertEquals($admin->id, $order->closing_approval_actioned_by);
        $this->assertEquals('Approved as volume exception', $order->closing_approval_notes);

        // Employee receives notification
        $this->assertEquals(1, $employee->notifications()->count());
        $this->assertStringContainsString('Approved', $employee->notifications()->first()->data['title']);
    }

    public function test_super_admin_can_accept_with_adjusted_closing_value(): void
    {
        $admin = Admin::factory()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'product_value' => 1000,
            'minimum_closing_price_snapshot' => 800,
            'closing_value' => 650,
            'closing_value_status' => 'Requested',
        ]);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.orders.closing-approval.accept', $order), [
            'adjusted_closing_value' => 750, // Counter-offer
            'notes' => 'Negotiated middle rate',
        ]);

        $response->assertSessionHas('status');
        $order->refresh();

        $this->assertEquals('Accepted', $order->closing_value_status);
        $this->assertEquals(750, (float) $order->closing_value);
        $this->assertEquals(250, (float) $order->discount_amount);
    }

    public function test_super_admin_can_reject_closing_value_with_mandatory_reason(): void
    {
        $admin = Admin::factory()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'closing_value' => 600,
            'closing_value_status' => 'Requested',
            'order_status' => 'pending',
        ]);

        // Attempt rejection without reason fails validation
        $response = $this->actingAs($admin, 'admin')->post(route('admin.orders.closing-approval.reject', $order), [
            'rejection_reason' => '',
        ]);
        $response->assertSessionHasErrors('rejection_reason');

        // Provide mandatory reason
        $response = $this->actingAs($admin, 'admin')->post(route('admin.orders.closing-approval.reject', $order), [
            'rejection_reason' => 'Discount exceeds corporate maximum allowance.',
        ]);

        $response->assertSessionHas('status');
        $order->refresh();

        $this->assertEquals('Rejected', $order->closing_value_status);
        $this->assertEquals('suspended', $order->order_status);
        $this->assertEquals($admin->id, $order->closing_approval_actioned_by);
        $this->assertEquals('Discount exceeds corporate maximum allowance.', $order->closing_approval_notes);

        // Employee notification
        $this->assertEquals(1, $employee->notifications()->count());
        $this->assertStringContainsString('Rejected', $employee->notifications()->first()->data['title']);
    }
}
