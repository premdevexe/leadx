<?php

namespace Tests\Feature;

use App\Models\PermissionPreset;
use App\Models\Team;
use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndSidebarCustomizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_default_employee_sees_enabled_dashboard_cards_and_sidebar_menus(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($employee, 'employee')->get(route('employee.dashboard'));
        $response->assertStatus(200);

        // Check cards
        $response->assertSee('My Total Orders');
        $response->assertSee('My Verified Revenue');
        $response->assertSee('Active Subscriptions');

        // Check sidebar items
        $response->assertSee('Create Order');
        $response->assertSee('My Orders');
        $response->assertSee('Customers (CRM)');
    }

    public function test_denying_dashboard_card_permission_hides_the_card(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        // Deny total revenue card
        app(PermissionService::class)->setEmployeeOverride($employee, 'dashboard.total_revenue', false);

        $response = $this->actingAs($employee, 'employee')->get(route('employee.dashboard'));
        $response->assertStatus(200);

        $response->assertSee('My Total Orders');
        $response->assertDontSee('My Verified Revenue');
    }

    public function test_denying_sidebar_menu_hides_the_navigation_link(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        // Deny create_order in sidebar
        app(PermissionService::class)->setEmployeeOverride($employee, 'sidebar.create_order', false);

        $response = $this->actingAs($employee, 'employee')->get(route('employee.dashboard'));
        $response->assertStatus(200);

        $response->assertDontSee('Create Order</a>', false);
    }

    public function test_route_level_protection_blocks_disabled_module_actions(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        // Revoke orders.create permission
        app(PermissionService::class)->setEmployeeOverride($employee, 'orders.create', false);

        // GET /employee/orders/create must be forbidden (403)
        $response = $this->actingAs($employee, 'employee')->get(route('employee.orders.create'));
        $response->assertStatus(403);

        // POST /employee/orders must be forbidden (403)
        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Attempted Buyer',
        ]);
        $response->assertStatus(403);
    }

    public function test_viewer_preset_has_read_only_access_and_cannot_create_orders(): void
    {
        $team = Team::factory()->create();
        $viewerPreset = PermissionPreset::where('slug', 'viewer')->first();
        $employee = User::factory()->create([
            'team_id' => $team->id,
            'preset_id' => $viewerPreset->id,
        ]);

        // Viewer can see dashboard and orders list
        $response = $this->actingAs($employee, 'employee')->get(route('employee.dashboard'));
        $response->assertStatus(200);

        $response = $this->actingAs($employee, 'employee')->get(route('employee.orders'));
        $response->assertStatus(200);

        // Viewer cannot access create order page
        $response = $this->actingAs($employee, 'employee')->get(route('employee.orders.create'));
        $response->assertStatus(403);
    }
}
