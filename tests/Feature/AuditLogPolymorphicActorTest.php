<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPolymorphicActorTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_with_legacy_api_request_type_does_not_crash_eager_loading(): void
    {
        // Insert audit log with 'api_request' auditable_type (as previously created by middleware)
        AuditLog::create([
            'auditable_type' => 'api_request',
            'auditable_id' => 0,
            'event' => 'api.employee.request',
            'new_values' => ['path' => 'api/v1/employee/dashboard'],
            'ip' => '127.0.0.1',
        ]);

        // Insert audit log with an unknown non-existent class
        AuditLog::create([
            'auditable_type' => 'completely_unknown_type',
            'auditable_id' => 999,
            'event' => 'system.unknown.event',
            'new_values' => [],
            'ip' => '127.0.0.1',
        ]);

        // Eager load with actor - must NOT throw Class "api_request" not found
        $activities = AuditLog::with('actor')->latest()->take(6)->get();

        $this->assertCount(2, $activities);
        $this->assertNull($activities[0]->actor);
        $this->assertNull($activities[1]->actor);
    }

    public function test_admin_dashboard_renders_successfully_with_api_request_audit_logs(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'admin@leadx.com',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
            'is_super_admin' => true,
        ]);

        AuditLog::create([
            'auditable_type' => 'api_request',
            'auditable_id' => 0,
            'event' => 'api.employee.request',
            'new_values' => ['path' => 'api/v1/employee/orders'],
            'ip' => '127.0.0.x',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('System');
    }

    public function test_root_slug_redirects_to_employee_login_for_unauthenticated_visitors(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('employee.login'));
    }

    public function test_root_slug_redirects_to_employee_dashboard_when_logged_in_as_employee(): void
    {
        $team = \App\Models\Team::factory()->create();
        $employee = \App\Models\User::factory()->create(['team_id' => $team->id, 'role' => 'employee']);

        $response = $this->actingAs($employee, 'employee')->get('/');
        $response->assertRedirect(route('employee.dashboard'));
    }

    public function test_root_slug_does_not_redirect_to_admin_even_if_admin_is_authenticated(): void
    {
        $admin = Admin::create([
            'name' => 'Admin User',
            'username' => 'adminuser',
            'email' => 'adminuser@leadx.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        // When logged in as admin, visiting / must NOT redirect to admin dashboard; it directs to employee login
        $response = $this->actingAs($admin, 'admin')->get('/');
        $response->assertRedirect(route('employee.login'));
    }

    public function test_admin_slug_redirects_to_admin_dashboard_when_authenticated_and_admin_login_when_guest(): void
    {
        // Unauthenticated visitor to /admin
        $this->get('/admin')->assertRedirect(route('admin.login'));

        // Authenticated admin to /admin
        $admin = Admin::create([
            'name' => 'Admin User 2',
            'username' => 'adminuser2',
            'email' => 'adminuser2@leadx.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect(route('admin.dashboard'));
    }
}
