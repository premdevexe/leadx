<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamDataAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_employee_without_grant_cannot_switch_to_team_data(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($employee, 'employee')->get(route('employee.dashboard', ['view' => 'team_data']));
        $response->assertRedirect(route('employee.dashboard', ['view' => 'my_data']));
        $response->assertSessionHas('warning');

        $response = $this->actingAs($employee, 'employee')->get(route('employee.orders', ['view' => 'team_data']));
        $response->assertRedirect(route('employee.orders', ['view' => 'my_data']));
        $response->assertSessionHas('warning');
    }

    public function test_employee_without_grant_cannot_view_another_employee_order(): void
    {
        $team = Team::factory()->create();
        $emp1 = User::factory()->create(['team_id' => $team->id]);
        $emp2 = User::factory()->create(['team_id' => $team->id]);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp2->id,
        ]);

        $response = $this->actingAs($emp1, 'employee')->get(route('employee.orders.show', $order));
        $response->assertStatus(403);
    }

    public function test_admin_can_grant_and_revoke_team_data_access(): void
    {
        $admin = Admin::factory()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        // 1. Admin grants access window
        $response = $this->actingAs($admin, 'admin')->post(route('admin.employees.team-access.grant', $employee), [
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
        $response->assertSessionHas('status');

        $grant = EmployeeTeamAccessGrant::where('user_id', $employee->id)->first();
        $this->assertNotNull($grant);
        $this->assertTrue($grant->isActive());
        $this->assertTrue($employee->fresh()->hasActiveTeamAccess());

        // 2. Admin revokes access immediately
        $response = $this->actingAs($admin, 'admin')->post(route('admin.employees.team-access.revoke', $grant));
        $response->assertSessionHas('status');

        $this->assertFalse($grant->fresh()->isActive());
        $this->assertFalse($employee->fresh()->hasActiveTeamAccess());
    }

    public function test_employee_with_active_grant_can_view_team_orders_within_window(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();

        $emp1 = User::factory()->create(['team_id' => $team->id]);
        $emp2 = User::factory()->create(['team_id' => $team->id]);
        $otherEmp = User::factory()->create(['team_id' => $otherTeam->id]);

        // Grant access for last 7 days through today
        EmployeeTeamAccessGrant::create([
            'user_id' => $emp1->id,
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
        app(\App\Services\PermissionService::class)->setEmployeeOverride($emp1, 'team_data.view', true);

        // Order 1: within window, same team, assigned to emp2
        $inWindowOrder = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp2->id,
            'order_number' => 'TEAM-IN-001',
            'created_at' => now()->subDays(2),
        ]);

        // Order 2: outside window (15 days ago), same team
        $outOfWindowOrder = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp2->id,
            'order_number' => 'TEAM-OUT-002',
            'created_at' => now()->subDays(15),
        ]);

        // Order 3: within window, but different team
        $otherTeamOrder = SalesOrder::factory()->create([
            'team_id' => $otherTeam->id,
            'assigned_employee_id' => $otherEmp->id,
            'order_number' => 'OTHER-003',
            'created_at' => now()->subDays(2),
        ]);

        // Emp1 visits Team Data dashboard
        $response = $this->actingAs($emp1, 'employee')->get(route('employee.dashboard', ['view' => 'team_data']));
        $response->assertStatus(200);
        $response->assertSee('Team Performance Dashboard');
        $response->assertSee('TEAM-IN-001');

        // Emp1 visits Team Data orders list
        $response = $this->actingAs($emp1, 'employee')->get(route('employee.orders', ['view' => 'team_data']));
        $response->assertStatus(200);
        $response->assertSee('TEAM-IN-001');
        $response->assertDontSee('TEAM-OUT-002');
        $response->assertDontSee('OTHER-003');

        // Emp1 can view in-window order detail in view-only mode
        $response = $this->actingAs($emp1, 'employee')->get(route('employee.orders.show', $inWindowOrder));
        $response->assertStatus(200);
        $response->assertSee('Team Data Mode (View-Only)');

        // Emp1 CANNOT view out-of-window order detail
        $response = $this->actingAs($emp1, 'employee')->get(route('employee.orders.show', $outOfWindowOrder));
        $response->assertStatus(403);

        // Emp1 CANNOT view other team's order detail
        $response = $this->actingAs($emp1, 'employee')->get(route('employee.orders.show', $otherTeamOrder));
        $response->assertStatus(403);
    }
}
