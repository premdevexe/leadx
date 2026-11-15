<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\EmployeePermission;
use App\Models\Permission;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeySecurityAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    public function test_super_admin_can_store_and_update_customer_api_key(): void
    {
        $admin = Admin::factory()->create();
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'api_key_required_snapshot' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.api-key.store', $order), [
                'api_key' => 'sk-live-enterprise-secret-key-12345',
            ]);

        $response->assertRedirect();
        $this->assertTrue(OrderCredentialService::hasKey($order));

        $status = app(OrderCredentialService::class)->status($order);
        $this->assertTrue($status['configured']);
        $this->assertStringStartsWith('sk-', $status['masked_key']);
        $this->assertStringEndsWith('345', $status['masked_key']);
    }

    public function test_employee_cannot_store_customer_api_key(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'api_key_required_snapshot' => true,
        ]);

        $response = $this->actingAs($employee, 'employee')
            ->post(route('employee.orders.api-key.store', $order), [
                'api_key' => 'hacky-client-api-key',
            ]);

        $response->assertForbidden();
        $this->assertFalse(OrderCredentialService::hasKey($order));
    }

    public function test_employee_with_permission_can_reveal_api_key(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'api_key_required_snapshot' => true,
        ]);

        $admin = Admin::factory()->create();
        app(OrderCredentialService::class)->store($order, 'sk-confidential-token-9999', $admin);

        // Grant orders.view_api_key permission to employee
        $perm = Permission::where('name', 'orders.view_api_key')->first();
        EmployeePermission::create([
            'user_id' => $employee->id,
            'permission_id' => $perm->id,
            'granted' => true,
        ]);

        $response = $this->actingAs($employee, 'employee')
            ->post(route('employee.orders.api-key.reveal', $order));

        $response->assertRedirect();
        $this->assertEquals('sk-confidential-token-9999', session('order_api_key_reveal')['key'] ?? null);
    }

    public function test_employee_without_permission_cannot_reveal_api_key(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'api_key_required_snapshot' => true,
        ]);

        $admin = Admin::factory()->create();
        app(OrderCredentialService::class)->store($order, 'sk-confidential-token-9999', $admin);

        // Deny orders.view_api_key
        $perm = Permission::where('name', 'orders.view_api_key')->first();
        EmployeePermission::create([
            'user_id' => $employee->id,
            'permission_id' => $perm->id,
            'granted' => false,
        ]);

        $response = $this->actingAs($employee, 'employee')
            ->post(route('employee.orders.api-key.reveal', $order));

        $response->assertForbidden();
    }
}
