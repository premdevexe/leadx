<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_can_create_product_with_minimum_closing_price_and_api_key_requirement(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.products.store'), [
            'name' => 'Enterprise CRM Suite',
            'price' => 1200.00,
            'minimum_closing_price' => 950.00,
            'duration' => '1y',
            'api_key_required' => 1,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.products'));

        $this->assertDatabaseHas('products', [
            'name' => 'Enterprise CRM Suite',
            'price' => 1200.00,
            'minimum_closing_price' => 950.00,
            'api_key_required' => 1,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_update_product_floor_price_and_api_key_requirement(): void
    {
        $admin = Admin::factory()->create();
        $product = Product::factory()->create([
            'price' => 500,
            'minimum_closing_price' => 400,
            'api_key_required' => false,
        ]);

        $response = $this->actingAs($admin, 'admin')->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'price' => 600,
            'minimum_closing_price' => 450,
            'duration' => '6m',
            'api_key_required' => 1,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.products'));

        $product->refresh();
        $this->assertEquals(600, (float) $product->price);
        $this->assertEquals(450, (float) $product->minimum_closing_price);
        $this->assertTrue($product->api_key_required);
    }

    public function test_inactive_products_cannot_be_selected_by_employee(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);
        $inactiveProduct = Product::factory()->inactive()->create(['name' => 'Legacy Plan']);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Test Client',
            'product_id' => $inactiveProduct->id,
            'duration' => '1y',
            'closing_value' => 500,
        ]);

        $response->assertStatus(404);
        $this->assertEquals(0, SalesOrder::count());
    }

    public function test_order_creation_captures_snapshots_and_approves_when_above_minimum(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);
        $product = Product::factory()->create([
            'name' => 'Pro AI License',
            'price' => 1000.00,
            'minimum_closing_price' => 800.00,
            'duration' => '1y',
            'api_key_required' => true,
        ]);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Elite Customer',
            'customer_phone' => '+1234567890',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => 1000.00, // Full price -> Accepted immediately
        ]);

        $response->assertRedirect();
        $order = SalesOrder::first();

        $this->assertNotNull($order);
        $this->assertEquals('Pro AI License', $order->product_name_snapshot);
        $this->assertEquals(1000.00, (float) $order->product_price_snapshot);
        $this->assertEquals(800.00, (float) $order->minimum_closing_price_snapshot);
        $this->assertTrue($order->api_key_required_snapshot);
        $this->assertEquals('Accepted', $order->closing_value_status);
        $this->assertNull($order->closing_approval_requested_at);
    }

    public function test_order_creation_below_minimum_floor_price_is_rejected(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);
        $product = Product::factory()->create([
            'name' => 'Pro AI License',
            'price' => 1000.00,
            'minimum_closing_price' => 800.00,
            'duration' => '1y',
            'api_key_required' => true,
        ]);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Discount Buyer',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => 750.00, // Below floor of 800 -> must be rejected
        ]);

        $response->assertSessionHasErrors('closing_value');
        $this->assertNull(SalesOrder::first());
    }

    public function test_order_creation_between_floor_and_full_price_triggers_approval_requested(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);
        $product = Product::factory()->create([
            'name' => 'Pro AI License',
            'price' => 1000.00,
            'minimum_closing_price' => 800.00,
            'duration' => '1y',
            'api_key_required' => true,
        ]);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Discount Buyer',
            'customer_phone' => '+1234567890',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => 850.00, // Above floor of 800, below full price 1000
        ]);

        $response->assertRedirect();
        $order = SalesOrder::first();

        $this->assertNotNull($order);
        $this->assertEquals(850.00, (float) $order->closing_value);
        $this->assertEquals(800.00, (float) $order->minimum_closing_price_snapshot);
        $this->assertEquals('Requested', $order->closing_value_status);
        $this->assertNotNull($order->closing_approval_requested_at);
    }
}
