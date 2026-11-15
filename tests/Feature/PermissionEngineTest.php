<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\EmployeePermission;
use App\Models\EmployeePermissionHistory;
use App\Models\Permission;
use App\Models\PermissionPreset;
use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionEngineTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->service = app(PermissionService::class);
    }

    public function test_default_presets_and_permissions_are_seeded(): void
    {
        $this->assertDatabaseHas('permission_presets', ['slug' => 'admin']);
        $this->assertDatabaseHas('permission_presets', ['slug' => 'sales_executive', 'is_default' => true]);
        $this->assertDatabaseHas('permission_presets', ['slug' => 'viewer']);

        $this->assertDatabaseHas('permissions', ['name' => 'orders.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'orders.create']);
        $this->assertDatabaseHas('permissions', ['name' => 'orders.delete']);
        $this->assertDatabaseHas('permissions', ['name' => 'orders.complete']);
        $this->assertDatabaseHas('permissions', ['name' => 'team_data.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'dashboard.orders_count']);
        $this->assertDatabaseHas('permissions', ['name' => 'sidebar.orders']);
    }

    public function test_employee_inherits_assigned_preset_permissions(): void
    {
        $salesPreset = PermissionPreset::where('slug', 'sales_executive')->first();
        $viewerPreset = PermissionPreset::where('slug', 'viewer')->first();

        $salesEmployee = User::factory()->create(['preset_id' => $salesPreset->id]);
        $viewerEmployee = User::factory()->create(['preset_id' => $viewerPreset->id]);

        // Sales executive can view and create orders, but cannot delete orders
        $this->assertTrue($this->service->hasPermission($salesEmployee, 'orders.view'));
        $this->assertTrue($this->service->hasPermission($salesEmployee, 'orders.create'));
        $this->assertFalse($this->service->hasPermission($salesEmployee, 'orders.delete'));

        // Viewer can view orders, but cannot create orders
        $this->assertTrue($this->service->hasPermission($viewerEmployee, 'orders.view'));
        $this->assertFalse($this->service->hasPermission($viewerEmployee, 'orders.create'));
    }

    public function test_individual_employee_override_takes_precedence_over_preset(): void
    {
        $salesPreset = PermissionPreset::where('slug', 'sales_executive')->first();
        $employee = User::factory()->create(['preset_id' => $salesPreset->id]);

        // By preset, delete is false
        $this->assertFalse($this->service->hasPermission($employee, 'orders.delete'));

        // Admin grants delete override
        $this->service->setEmployeeOverride($employee, 'orders.delete', true, null, 'Special promotion grant');
        $this->assertTrue($this->service->hasPermission($employee, 'orders.delete'));

        // Admin revokes orders.create override
        $this->service->setEmployeeOverride($employee, 'orders.create', false, null, 'Suspended create access');
        $this->assertFalse($this->service->hasPermission($employee, 'orders.create'));
    }

    public function test_removing_override_reverts_to_preset(): void
    {
        $salesPreset = PermissionPreset::where('slug', 'sales_executive')->first();
        $employee = User::factory()->create(['preset_id' => $salesPreset->id]);

        // Deny orders.create
        $this->service->setEmployeeOverride($employee, 'orders.create', false);
        $this->assertFalse($this->service->hasPermission($employee, 'orders.create'));

        // Remove override
        $this->service->removeEmployeeOverride($employee, 'orders.create');
        $this->assertTrue($this->service->hasPermission($employee, 'orders.create'));
    }

    public function test_resetting_employee_clears_all_overrides(): void
    {
        $salesPreset = PermissionPreset::where('slug', 'sales_executive')->first();
        $employee = User::factory()->create(['preset_id' => $salesPreset->id]);

        $this->service->setEmployeeOverride($employee, 'orders.delete', true);
        $this->service->setEmployeeOverride($employee, 'orders.create', false);

        $this->assertEquals(2, EmployeePermission::where('user_id', $employee->id)->count());

        $this->service->resetEmployeeToPreset($employee);

        $this->assertEquals(0, EmployeePermission::where('user_id', $employee->id)->count());
        $this->assertTrue($this->service->hasPermission($employee, 'orders.create'));
        $this->assertFalse($this->service->hasPermission($employee, 'orders.delete'));
    }

    public function test_preset_application_strategy_all_employees(): void
    {
        $viewerPreset = PermissionPreset::where('slug', 'viewer')->first();
        $employees = User::factory()->count(3)->create();

        $this->service->applyPresetToEmployees($viewerPreset, 'all');

        foreach ($employees as $emp) {
            $this->assertEquals($viewerPreset->id, $emp->fresh()->preset_id);
            $this->assertFalse($this->service->hasPermission($emp->fresh(), 'orders.create'));
        }
    }

    public function test_preset_application_strategy_new_only(): void
    {
        $viewerPreset = PermissionPreset::where('slug', 'viewer')->first();

        $this->service->applyPresetToEmployees($viewerPreset, 'new_only');

        $this->assertTrue($viewerPreset->fresh()->is_default);
        $this->assertFalse(PermissionPreset::where('slug', 'sales_executive')->first()->is_default);
    }

    public function test_preset_application_strategy_selected_employees(): void
    {
        $viewerPreset = PermissionPreset::where('slug', 'viewer')->first();
        $emp1 = User::factory()->create();
        $emp2 = User::factory()->create();

        $this->service->applyPresetToEmployees($viewerPreset, 'selected', [$emp1->id]);

        $this->assertEquals($viewerPreset->id, $emp1->fresh()->preset_id);
        $this->assertNotEquals($viewerPreset->id, $emp2->fresh()->preset_id);
    }

    public function test_modifications_are_logged_to_history_and_purged_after_30_days(): void
    {
        $admin = Admin::factory()->create();
        $employee = User::factory()->create();

        $this->service->setEmployeeOverride($employee, 'orders.create', false, $admin->id, 'Compliance review');

        $this->assertDatabaseHas('employee_permission_histories', [
            'admin_id' => $admin->id,
            'user_id' => $employee->id,
            'new_value' => false,
            'reason' => 'Compliance review',
        ]);

        // Create an old history record (35 days ago) using DB table insert to preserve historical timestamp
        \Illuminate\Support\Facades\DB::table('employee_permission_histories')->insert([
            'admin_id' => $admin->id,
            'user_id' => $employee->id,
            'permission_id' => Permission::where('name', 'orders.view')->first()->id,
            'old_value' => true,
            'new_value' => false,
            'reason' => 'Old record',
            'created_at' => now()->subDays(35)->toDateTimeString(),
            'updated_at' => now()->subDays(35)->toDateTimeString(),
        ]);

        $deleted = $this->service->cleanOldHistory();
        $this->assertEquals(1, $deleted);
    }

    public function test_admin_ui_preset_and_employee_permission_management(): void
    {
        $admin = Admin::factory()->create();
        $employee = User::factory()->create();

        // 1. Admin can access presets list
        $response = $this->actingAs($admin, 'admin')->get(route('admin.presets'));
        $response->assertStatus(200);
        $response->assertSee('Baseline Presets');

        // 2. Admin can view create form
        $response = $this->actingAs($admin, 'admin')->get(route('admin.presets.create'));
        $response->assertStatus(200);

        // 3. Admin can create preset
        $orderPerm = Permission::where('name', 'orders.view')->first();
        $response = $this->actingAs($admin, 'admin')->post(route('admin.presets.store'), [
            'name' => 'Custom Trainee',
            'description' => 'Trainee test preset',
            'permissions' => [$orderPerm->id],
        ]);
        $response->assertRedirect(route('admin.presets'));
        $this->assertDatabaseHas('permission_presets', ['name' => 'Custom Trainee']);

        // 4. Admin can view employee permission override page
        $response = $this->actingAs($admin, 'admin')->get(route('admin.presets.employee', $employee));
        $response->assertStatus(200);
        $response->assertSee($employee->name);

        // 5. Admin can update employee override via UI
        $response = $this->actingAs($admin, 'admin')->put(route('admin.presets.employee.update', $employee), [
            'permissions' => [
                (string) $orderPerm->id => 'deny',
            ],
            'reason' => 'Revoked by admin UI',
        ]);
        $response->assertRedirect(route('admin.presets.employee', $employee));
        $this->assertFalse($this->service->hasPermission($employee, 'orders.view'));
    }
}
