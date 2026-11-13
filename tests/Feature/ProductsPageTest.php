<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_admin_can_see_products_index(): void
    {
        Product::factory()->create(['name' => 'Starter Pack', 'price' => 19.99]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.products'))
            ->assertOk()
            ->assertSee('Starter Pack');
    }

    public function test_admin_can_create_product(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.products.store'), [
                'name' => 'Pro Plan',
                'price' => '49.00',
                'description' => 'Best seller',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.products'));

        $this->assertDatabaseHas('products', ['name' => 'Pro Plan', 'price' => '49.00']);
    }

    public function test_admin_cannot_create_duplicate_product_name(): void
    {
        Product::factory()->create(['name' => 'Duo']);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.products.store'), ['name' => 'Duo', 'price' => '1.00'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Product::where('name', 'Duo')->count());
    }

    public function test_admin_can_update_and_delete_product(): void
    {
        $product = Product::factory()->create(['name' => 'Legacy', 'price' => 5]);

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.products.update', $product), ['name' => 'Legacy', 'price' => '9.99'])
            ->assertRedirect(route('admin.products'));

        $this->assertSame('9.99', $product->fresh()->price);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_orders_create_page_fetches_products_and_durations(): void
    {
        Product::factory()->create(['name' => 'Team Plan', 'price' => 99]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->assertSee('Team Plan')                    // product is offered
            ->assertSee('Subscription duration');          // duration picker present
    }

    public function test_order_stores_snapshot_of_selected_product_and_duration(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $employee = User::factory()->create(['team_id' => $team->id]);
        $product = Product::factory()->create(['name' => 'Snapshot Me', 'price' => 120.5, 'duration' => '7d']);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.orders.store'), [
                'team_id' => $team->id,
                'assigned_employee_id' => $employee->id,
                'customer_name' => 'Acme Inc',
                'product_id' => $product->id,
                'duration' => '7d',
                'payment_status' => 'pending',
                'order_status' => 'pending',
            ])
            ->assertSessionHasNoErrors();

        $order = SalesOrder::first();

        // Denormalized snapshots survive later product edits.
        $this->assertSame('Snapshot Me', $order->product_name);
        $this->assertSame('7d', $order->duration);
        $this->assertSame('120.50', (string) $order->product_value);

        $product->update(['name' => 'Renamed', 'price' => 999]);
        $this->assertSame('Snapshot Me', $order->fresh()->product_name);
    }

    public function test_admin_can_search_and_filter_products(): void
    {
        Product::factory()->create(['name' => 'Alpha Basic', 'status' => 'active']);
        Product::factory()->create(['name' => 'Beta Pro', 'status' => 'inactive']);

        $admin = $this->admin();

        // Search by name
        $this->actingAs($admin, 'admin')
            ->get(route('admin.products', ['search' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Basic')
            ->assertDontSee('Beta Pro');

        // Filter by status
        $this->actingAs($admin, 'admin')
            ->get(route('admin.products', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Beta Pro')
            ->assertDontSee('Alpha Basic');
    }

    public function test_admin_can_toggle_product_status(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin(), 'admin')
            ->patch(route('admin.products.toggle-status', $product))
            ->assertRedirect(route('admin.products'));

        $this->assertSame('inactive', $product->fresh()->status);

        $this->actingAs($this->admin(), 'admin')
            ->patch(route('admin.products.toggle-status', $product))
            ->assertRedirect(route('admin.products'));

        $this->assertSame('active', $product->fresh()->status);
    }

    public function test_deleting_product_with_orders_archives_safely(): void
    {
        $product = Product::factory()->create(['name' => 'In Use Plan', 'status' => 'active']);
        $team = Team::factory()->create(['team_code' => 'ARC']);
        SalesOrder::factory()->create([
            'team_id' => $team->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products'));

        // Product is not deleted; it is archived (inactive)
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'inactive']);
    }

    public function test_dashboard_renders_dynamic_product_analytics(): void
    {
        Product::factory()->create(['name' => 'Analytics Product', 'status' => 'active']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dynamic Product Analytics')
            ->assertSee('Analytics Product');
    }
}
