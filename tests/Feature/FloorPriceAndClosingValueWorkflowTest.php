<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorPriceAndClosingValueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RefundStatusSeeder::class);
    }

    public function test_employee_cannot_submit_closing_value_below_minimum_floor_price(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        $product = Product::factory()->create([
            'price' => 1000.00,
            'minimum_closing_price' => 750.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Alice Floor Buyer',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => 600.00, // strictly below 750.00 floor
        ]);

        $response->assertSessionHasErrors('closing_value');
        $this->assertDatabaseMissing('sales_orders', [
            'customer_name' => 'Alice Floor Buyer',
        ]);
    }

    public function test_employee_submitting_full_price_is_accepted_immediately(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        $product = Product::factory()->create([
            'price' => 1000.00,
            'minimum_closing_price' => 750.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Bob Full Price Buyer',
            'customer_phone' => '+1122334455',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => 1000.00,
        ]);

        $response->assertRedirect();
        $order = SalesOrder::where('customer_name', 'Bob Full Price Buyer')->first();
        $this->assertNotNull($order);
        $this->assertEquals('Accepted', $order->closing_value_status);
        $this->assertNull($order->closing_approval_requested_at);
        $this->assertEquals(0.0, (float) $order->discount_amount);
    }

    public function test_employee_submitting_discount_above_floor_enters_requested_status(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'status' => 'active']);
        $product = Product::factory()->create([
            'price' => 1000.00,
            'minimum_closing_price' => 750.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Charlie Discount Buyer',
            'customer_phone' => '+1122334466',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => 850.00, // Valid discount between 750 and 1000
        ]);

        $response->assertRedirect();
        $order = SalesOrder::where('customer_name', 'Charlie Discount Buyer')->first();
        $this->assertNotNull($order);
        $this->assertEquals('Requested', $order->closing_value_status);
        $this->assertNotNull($order->closing_approval_requested_at);
        $this->assertEquals(150.00, (float) $order->discount_amount);
    }

    public function test_super_admin_can_accept_and_reject_requested_closing_value(): void
    {
        $admin = Admin::factory()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'product_value' => 1000.00,
            'closing_value' => 850.00,
            'minimum_closing_price_snapshot' => 800.00,
            'closing_value_status' => 'Requested',
            'closing_approval_requested_at' => now(),
        ]);

        // Accept
        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.closing-approval.accept', $order), [
                'notes' => 'Approved corporate discount',
            ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('Accepted', $order->closing_value_status);
        $this->assertEquals($admin->id, $order->closing_approval_actioned_by);

        // Reject
        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.closing-approval.reject', $order), [
                'rejection_reason' => 'Margin too thin',
            ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals('Rejected', $order->closing_value_status);
        $this->assertEquals('Margin too thin', $order->closing_approval_notes);
    }

    public function test_employee_can_resubmit_rejected_closing_value_above_floor(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'product_price_snapshot' => 1000.00,
            'product_value' => 1000.00,
            'closing_value' => 800.00,
            'minimum_closing_price_snapshot' => 750.00,
            'closing_value_status' => 'Rejected',
        ]);

        // 1. Cannot resubmit below floor
        $failResponse = $this->actingAs($employee, 'employee')
            ->post(route('employee.orders.closing-approval.resubmit', $order), [
                'closing_value' => 700.00, // below 750 floor
                'resubmission_notes' => 'Trying lower',
            ]);
        $failResponse->assertSessionHasErrors('closing_value');

        // 2. Can resubmit at or above floor
        $successResponse = $this->actingAs($employee, 'employee')
            ->post(route('employee.orders.closing-approval.resubmit', $order), [
                'closing_value' => 900.00,
                'resubmission_notes' => 'Customer agreed to pay more',
            ]);

        $successResponse->assertRedirect();
        $order->refresh();

        $this->assertEquals('Requested', $order->closing_value_status);
        $this->assertEquals(900.00, (float) $order->closing_value);
        $this->assertEquals(100.00, (float) $order->discount_amount);
        $this->assertDatabaseHas('order_closing_value_histories', [
            'order_id' => $order->id,
            'actor_type' => 'employee',
            'actor_id' => $employee->id,
            'status' => 'Requested',
            'previous_value' => 800.00,
            'proposed_value' => 900.00,
        ]);
    }

    public function test_super_admin_can_modify_accepted_closing_value_with_mandatory_reason_and_audit(): void
    {
        $admin = Admin::factory()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'product_price_snapshot' => 1000.00,
            'product_value' => 1000.00,
            'closing_value' => 950.00,
            'minimum_closing_price_snapshot' => 800.00,
            'closing_value_status' => 'Accepted',
        ]);

        // Modify without reason fails validation
        $fail = $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.closing-value.modify', $order), [
                'new_closing_value' => 880.00,
                'modification_reason' => '',
            ]);
        $fail->assertSessionHasErrors('modification_reason');

        // Modify with valid reason
        $success = $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.closing-value.modify', $order), [
                'new_closing_value' => 880.00,
                'modification_reason' => 'Negotiated VIP enterprise rate directly with CEO',
            ]);

        $success->assertRedirect();
        $order->refresh();

        $this->assertEquals(880.00, (float) $order->closing_value);
        $this->assertEquals(120.00, (float) $order->discount_amount);
        $this->assertDatabaseHas('order_edit_histories', [
            'order_id' => $order->id,
            'admin_id' => $admin->id,
            'field_name' => 'closing_value',
            'old_value' => '950',
            'new_value' => '880',
            'reason' => 'Negotiated VIP enterprise rate directly with CEO',
        ]);
    }
}
