<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OrderApiCredential;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderCredentialService;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_employee_creates_order_with_server_side_team_derivation(): void
    {
        $team = Team::factory()->create(['team_code' => 'ALPHA']);
        $employee = User::factory()->create(['team_id' => $team->id]);
        $product = Product::factory()->create([
            'name' => 'Enterprise License',
            'price' => 500,
            'minimum_closing_price' => 400,
            'duration' => '1y',
        ]);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Acme Corp',
            'customer_email' => 'acme@example.com',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => 500, // Full price -> Accepted immediately
        ]);

        $order = SalesOrder::first();
        $this->assertNotNull($order);
        $this->assertEquals($team->id, $order->team_id);
        $this->assertEquals($employee->id, $order->assigned_employee_id);
        $this->assertEquals('Acme Corp', $order->customer_name);
        $this->assertEquals('Accepted', $order->closing_value_status);
        $this->assertStringStartsWith('ALPHA-', $order->order_number);
    }

    public function test_employee_without_team_cannot_create_order(): void
    {
        $employee = User::factory()->create(['team_id' => null]);
        $product = Product::factory()->create();

        $response = $this->actingAs($employee, 'employee')->get(route('employee.orders.create'));
        $response->assertRedirect(route('employee.orders'));
        $response->assertSessionHasErrors('team_id');

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Acme Corp',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => 450,
        ]);
        $response->assertSessionHasErrors('team_id');
        $this->assertEquals(0, SalesOrder::count());
    }

    public function test_employee_can_view_masked_key_and_reveal_when_permitted(): void
    {
        $admin = Admin::factory()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
        ]);

        // Super Admin stores encrypted API key (Section 3.2 Super Admin-Only API key entry)
        app(OrderCredentialService::class)->store($order, 'sk_live_secretkey123456789', $admin);

        // 1. Show page renders masked key
        $response = $this->actingAs($employee, 'employee')->get(route('employee.orders.show', $order));
        $response->assertStatus(200);
        $response->assertSee('Customer API Credential Delivery');
        $response->assertSee('Reveal Key');

        // 2. Employee reveals key
        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.api-key.reveal', $order));
        $response->assertRedirect();
        $response->assertSessionHas('order_api_key_reveal');
        $revealed = session('order_api_key_reveal');
        $this->assertEquals('sk_live_secretkey123456789', $revealed['key']);
    }

    public function test_employee_without_reveal_permission_cannot_reveal_api_key(): void
    {
        $admin = Admin::factory()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
        ]);

        app(OrderCredentialService::class)->store($order, 'sk_live_secretkey123456789', $admin);

        // Deny orders.view_api_key
        app(PermissionService::class)->setEmployeeOverride($employee, 'orders.view_api_key', false);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.api-key.reveal', $order));
        $response->assertStatus(403);
    }
}
