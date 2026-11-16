<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_features_remain_reachable_from_both_admin_layouts(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        foreach (['admin.dashboard', 'admin.presets', 'admin.settings'] as $page) {
            $response = $this->get(route($page))->assertOk();

            foreach (['admin.presets', 'admin.email.index', 'admin.api-settings.index', 'admin.backups.index', 'admin.installer.recovery'] as $destination) {
                $response->assertSee('href="'.route($destination).'"', false);
            }

            $response->assertSee('Skip to main content')
                ->assertSee('id="main-content"', false)
                ->assertSee('name="csrf-token"', false);
        }
    }

    public function test_employee_navigation_preserves_permissions_and_identifies_current_page(): void
    {
        $this->seed(PermissionSeeder::class);
        $employee = User::factory()->create(['team_id' => Team::factory()->create()->id]);

        $response = $this->actingAs($employee, 'employee')->get(route('employee.dashboard'))->assertOk();
        $response->assertSee('data-notifications-url="'.route('employee.notifications').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Skip to main content');
    }

    public function test_team_create_order_has_one_current_menu_item_and_no_placeholder_account_link(): void
    {
        // The team.web middleware authenticates via the session team_id key
        // (see AuthenticateTeamWeb), not via the 'team' guard — mirror the
        // pattern used in TeamPortalTest.
        $team = Team::factory()->create();
        $this->withSession(['team_id' => $team->id]);

        $response = $this->get(route('team.orders.create'))->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
        $response->assertDontSee('Profile &amp; Settings', false)
            ->assertSee('Skip to main content');
    }
}
