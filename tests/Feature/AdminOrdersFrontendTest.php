<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrdersFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_orders_index_renders(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $emp = User::factory()->create(['team_id' => $team->id]);
        SalesOrder::factory()->create(['team_id' => $team->id, 'assigned_employee_id' => $emp->id]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('GEM');
    }

    public function test_orders_create_and_detail_render_and_store_works(): void
    {
        $team = Team::factory()->create(['team_code' => 'CLA']);
        $emp = User::factory()->create(['team_id' => $team->id]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.orders.store'), [
                'team_id' => $team->id,
                'assigned_employee_id' => $emp->id,
                'customer_name' => 'Ada Lovelace',
                'customer_phone' => '+15551234567',
                'product_name' => 'Pro',
                'product_value' => 100,
                'closing_value' => 100,
                'payment_status' => 'paid',
                'order_status' => 'pending',
            ])
            ->assertRedirect(route('admin.orders.show', SalesOrder::first()));

        $order = SalesOrder::first();
        $this->assertSame('CLA-'.now()->format('Y').'-000001', $order->order_number);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('WhatsApp share');
    }

    public function test_team_code_is_locked_after_first_order_in_admin_panel(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $emp = User::factory()->create(['team_id' => $team->id]);
        SalesOrder::factory()->create(['team_id' => $team->id]);

        // Changing the code is rejected (validation error on team_code).
        $this->actingAs($admin, 'admin')
            ->from(route('admin.teams.edit', $team))
            ->put(route('admin.teams.update', $team), [
                'team_code' => 'XYZ',
                'team_name' => $team->team_name,
                'username' => $team->username,
            ])
            ->assertRedirect(route('admin.teams.edit', $team))
            ->assertSessionHasErrors('team_code');

        // Keeping the same code is allowed.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.teams.update', $team), [
                'team_code' => 'GEM',
                'team_name' => 'Renamed',
                'username' => $team->username,
            ])
            ->assertRedirect(route('admin.teams'));
    }

    public function test_invalid_team_code_format_rejected_on_create(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.teams.create'))
            ->post(route('admin.teams.store'), [
                'team_code' => 'NEW-001', // not a valid format
                'team_name' => 'T',
                'username' => 'u-'.bin2hex(random_bytes(4)),
                'password' => 'secret-123',
                'password_confirmation' => 'secret-123',
            ])
            ->assertSessionHasErrors('team_code');
    }

    public function test_order_edit_page_renders_with_prefilled_data(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $emp = User::factory()->create(['team_id' => $team->id]);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
            'customer_name' => 'Original Customer',
            'customer_phone' => '+15551234567',
            'product_name' => 'Original Package',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.edit', $order))
            ->assertOk()
            ->assertSee('Edit Order')
            ->assertSee('Original Customer')
            ->assertSee('+15551234567')
            ->assertSee('Original Package');
    }

    public function test_admin_can_update_order_details(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $emp1 = User::factory()->create(['team_id' => $team->id]);
        $emp2 = User::factory()->create(['team_id' => $team->id]);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp1->id,
            'customer_name' => 'Old Name',
            'closing_value' => 100,
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.orders.update', $order), [
                'assigned_employee_id' => $emp2->id,
                'customer_name' => 'Updated Customer',
                'customer_phone' => '+15559876543',
                'customer_email' => 'updated@example.com',
                'product_name' => 'Custom Elite Plan',
                'duration' => '6m',
                'product_value' => 250,
                'discount_amount' => 50,
                'closing_value' => 200,
                'amount_received' => 200,
                'payment_status' => 'paid',
                'order_status' => 'active',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertSame('Updated Customer', $order->customer_name);
        $this->assertSame('+15559876543', $order->customer_phone);
        $this->assertSame('updated@example.com', $order->customer_email);
        $this->assertSame('Custom Elite Plan', $order->product_name);
        $this->assertSame('6m', $order->duration);
        $this->assertEquals(200.00, $order->closing_value);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('active', $order->order_status);
        $this->assertSame($emp2->id, $order->assigned_employee_id);
    }

    public function test_admin_order_index_and_show_contain_edit_links(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $order = SalesOrder::factory()->create(['team_id' => $team->id]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee(route('admin.orders.edit', $order));

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(route('admin.orders.edit', $order));
    }
}
