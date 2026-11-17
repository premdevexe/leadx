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
}
