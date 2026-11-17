<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ApiApplication;
use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ApiManagementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure LeadX API application exists
        ApiApplication::leadx();
    }

    public function test_super_admin_can_access_api_management_page(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.api-management.index'));

        $response->assertStatus(200);
        $response->assertSee('API Management');
        $response->assertSee('LeadX Android App');
        $response->assertSee('Total API Requests');
        $response->assertSee('API Key Management');
        $response->assertSee('Endpoint Monitoring');
        $response->assertSee('Request Logs', false);
        $response->assertSee('API Documentation');
    }

    public function test_employee_cannot_access_api_management(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAs($employee, 'employee')->get(route('admin.api-management.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_unauthenticated_visitor_is_redirected(): void
    {
        $response = $this->get(route('admin.api-management.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_api_key_generation_and_one_time_display(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.api-management.keys.generate'));

        $response->assertRedirect(route('admin.api-management.index', ['tab' => 'keys']));
        $response->assertSessionHas('new_api_key');

        $plainKey = session('new_api_key');
        $this->assertStringStartsWith('lx_live_', $plainKey);

        // Verify key in database
        $keyInDb = ApiKey::where('status', ApiKey::STATUS_ACTIVE)->first();
        $this->assertNotNull($keyInDb);
        $this->assertEquals(hash('sha256', $plainKey), $keyInDb->key_hash);

        // Assert plain key is NEVER stored in database table
        $this->assertDatabaseMissing('api_keys', ['name' => $plainKey]);
        $this->assertStringNotContainsString($plainKey, (string) $keyInDb->key_hash);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ApiKey::class,
            'auditable_id' => $keyInDb->id,
            'event' => 'api_key.generated',
        ]);

        // Follow redirect and ensure the one-time display banner is shown
        $followResponse = $this->actingAs($admin, 'admin')->get(route('admin.api-management.index', ['tab' => 'keys']));
        $followResponse->assertStatus(200);
        $followResponse->assertSee('One-Time Secret Display');
        $followResponse->assertSee($plainKey);

        // Subsequent request: flash is gone and key is never displayed again
        $subsequentResponse = $this->actingAs($admin, 'admin')->get(route('admin.api-management.index', ['tab' => 'keys']));
        $subsequentResponse->assertStatus(200);
        $subsequentResponse->assertDontSee($plainKey);
    }

    public function test_api_key_regeneration_revokes_old_key_and_issues_new(): void
    {
        $admin = Admin::factory()->create();
        $app = ApiApplication::leadx();
        $initial = ApiKey::createKey($app, $admin, 'Initial Key');
        $oldKey = $initial['key'];

        $response = $this->actingAs($admin, 'admin')->post(route('admin.api-management.keys.regenerate', $oldKey->id));

        $response->assertRedirect(route('admin.api-management.index', ['tab' => 'keys']));
        $response->assertSessionHas('new_api_key');

        $oldKey->refresh();
        $this->assertEquals(ApiKey::STATUS_REVOKED, $oldKey->status);
        $this->assertNotNull($oldKey->revoked_at);

        // Exactly 1 active key remains
        $activeKeys = $app->keys()->where('status', ApiKey::STATUS_ACTIVE)->get();
        $this->assertCount(1, $activeKeys);
        $this->assertNotEquals($oldKey->id, $activeKeys->first()->id);

        // Audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ApiKey::class,
            'event' => 'api_key.regenerated',
        ]);
    }

    public function test_api_key_revocation(): void
    {
        $admin = Admin::factory()->create();
        $app = ApiApplication::leadx();
        $keyResult = ApiKey::createKey($app, $admin);
        $key = $keyResult['key'];

        $this->assertEquals(ApiKey::STATUS_ACTIVE, $key->status);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.api-management.keys.revoke', $key->id));

        $response->assertRedirect(route('admin.api-management.index', ['tab' => 'keys']));

        $key->refresh();
        $this->assertEquals(ApiKey::STATUS_REVOKED, $key->status);
        $this->assertNotNull($key->revoked_at);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ApiKey::class,
            'auditable_id' => $key->id,
            'event' => 'api_key.revoked',
        ]);
    }

    public function test_api_key_enable_and_disable_toggle(): void
    {
        $admin = Admin::factory()->create();
        $app = ApiApplication::leadx();
        $key = ApiKey::createKey($app, $admin)['key'];

        // 1. Toggle to disabled
        $response = $this->actingAs($admin, 'admin')->post(route('admin.api-management.keys.toggle', $key->id));
        $response->assertRedirect(route('admin.api-management.index', ['tab' => 'keys']));
        $key->refresh();
        $this->assertEquals(ApiKey::STATUS_DISABLED, $key->status);

        // 2. Toggle back to active
        $this->actingAs($admin, 'admin')->post(route('admin.api-management.keys.toggle', $key->id));
        $key->refresh();
        $this->assertEquals(ApiKey::STATUS_ACTIVE, $key->status);
    }

    public function test_rate_limit_and_configuration_updates(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.api-management.config.update'), [
            'name' => 'LeadX Android App Enterprise',
            'base_url' => 'https://api.leadx.test/api/v1',
            'version' => 'v1.5',
            'rate_limit_per_minute' => 120,
            'key_expiry_days' => 45,
            'ip_allowlist' => '192.168.1.50, 10.0.0.0/24',
            'is_enabled' => 1,
            'maintenance_mode' => 0,
        ]);

        $response->assertRedirect(route('admin.api-management.index', ['tab' => 'config']));

        $app = ApiApplication::leadx()->fresh();
        $this->assertEquals('LeadX Android App Enterprise', $app->name);
        $this->assertEquals('https://api.leadx.test/api/v1', $app->base_url);
        $this->assertEquals('v1.5', $app->version);
        $this->assertEquals(120, $app->rate_limit_per_minute);
        $this->assertEquals(45, $app->key_expiry_days);
        $this->assertStringContainsString('192.168.1.50', $app->ip_allowlist);
        $this->assertTrue($app->is_enabled);
        $this->assertFalse($app->maintenance_mode);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ApiApplication::class,
            'event' => 'api_config.updated',
        ]);
    }

    public function test_configuration_validation_errors(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.api-management.config.update'), [
            'name' => '',
            'base_url' => 'invalid-url',
            'version' => '',
            'rate_limit_per_minute' => -10,
            'key_expiry_days' => -5,
        ]);

        $response->assertSessionHasErrors(['name', 'base_url', 'version', 'rate_limit_per_minute', 'key_expiry_days']);
    }

    public function test_health_monitor_endpoint(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.api-management.health'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'label',
            'code',
            'checked_at',
            'checks' => [
                'laravel_api',
                'database',
                'auth_middleware',
                'api_key',
            ],
        ]);

        // Security assertion: DB credentials or exception details must never be exposed
        $dbPass = config('database.connections.mysql.password');
        if (! empty($dbPass)) {
            $response->assertDontSee($dbPass);
        }
        $dbUser = config('database.connections.mysql.username');
        if (! empty($dbUser)) {
            $response->assertDontSee($dbUser);
        }
        $response->assertDontSee('Exception');
        $response->assertDontSee('SQLSTATE');
    }

    public function test_request_log_filtering(): void
    {
        $admin = Admin::factory()->create();

        // Seed sample request logs
        ApiRequestLog::create([
            'request_id' => 'req_sample_orders',
            'endpoint' => '/api/v1/employee/orders',
            'method' => 'GET',
            'status_code' => 200,
            'response_time_ms' => 42,
            'ip_address' => '192.168.1.x',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        ApiRequestLog::create([
            'request_id' => 'req_sample_error',
            'endpoint' => '/api/v1/employee/payments',
            'method' => 'POST',
            'status_code' => 422,
            'response_time_ms' => 88,
            'ip_address' => '10.0.0.x',
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        // Filter by endpoint 'orders'
        $responseOrders = $this->actingAs($admin, 'admin')->get(route('admin.api-management.index', [
            'tab' => 'logs',
            'endpoint' => 'orders',
        ]));
        $responseOrders->assertStatus(200);
        $responseOrders->assertSee('req_sample_orders');
        $responseOrders->assertDontSee('req_sample_error');

        // Filter by status '4xx'
        $responseErrors = $this->actingAs($admin, 'admin')->get(route('admin.api-management.index', [
            'tab' => 'logs',
            'status' => '4xx',
        ]));
        $responseErrors->assertStatus(200);
        $responseErrors->assertSee('req_sample_error');
        $responseErrors->assertDontSee('req_sample_orders');

        // Filter by method 'POST'
        $responsePost = $this->actingAs($admin, 'admin')->get(route('admin.api-management.index', [
            'tab' => 'logs',
            'method' => 'POST',
        ]));
        $responsePost->assertStatus(200);
        $responsePost->assertSee('req_sample_error');
        $responsePost->assertDontSee('req_sample_orders');
    }

    public function test_endpoint_monitoring_lists_actual_registered_routes(): void
    {
        $service = app(ApiManagementService::class);
        $grouped = $service->getGroupedEndpoints();

        $this->assertArrayHasKey('Authentication', $grouped);
        $this->assertArrayHasKey('Dashboard', $grouped);
        $this->assertArrayHasKey('Orders', $grouped);
        $this->assertArrayHasKey('Customers', $grouped);
        $this->assertArrayHasKey('Payments', $grouped);
        $this->assertArrayHasKey('Subscriptions', $grouped);
        $this->assertArrayHasKey('Follow-ups', $grouped);
        $this->assertArrayHasKey('Notifications', $grouped);
        $this->assertArrayHasKey('Calendar', $grouped);
        $this->assertArrayHasKey('Profile', $grouped);
        $this->assertArrayHasKey('Customer Issues', $grouped);

        // Verify that registered orders endpoints are present
        $orderUris = array_column($grouped['Orders'], 'uri');
        $this->assertTrue(
            in_array('/api/v1/employee/orders', $orderUris) || in_array('/api/v1/orders', $orderUris)
        );
    }

    public function test_middleware_enforces_maintenance_mode(): void
    {
        $app = ApiApplication::leadx();
        $app->update(['maintenance_mode' => true]);

        $response = $this->postJson('/api/v1/employee/login', [
            'login' => 'test@leadx.com',
            'password' => 'password',
        ]);

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'error',
            'code' => 503,
            'message' => 'API is currently in maintenance mode. Please try again later.',
        ]);
    }

    public function test_middleware_enforces_disabled_api(): void
    {
        $app = ApiApplication::leadx();
        $app->update(['is_enabled' => false, 'maintenance_mode' => false]);

        $response = $this->postJson('/api/v1/employee/login', [
            'login' => 'test@leadx.com',
            'password' => 'password',
        ]);

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'error',
            'code' => 503,
            'message' => 'API access is currently disabled.',
        ]);
    }

    public function test_documentation_endpoints(): void
    {
        $admin = Admin::factory()->create();

        // 1. OpenAPI
        $resOpenApi = $this->actingAs($admin, 'admin')->get(route('admin.api-management.docs.openapi'));
        $resOpenApi->assertStatus(200);

        // 2. Swagger UI
        $resSwagger = $this->actingAs($admin, 'admin')->get(route('admin.api-management.docs.swagger'));
        $resSwagger->assertStatus(200);
        $resSwagger->assertSee('SwaggerUIBundle', false);

        // 3. Postman Collection
        $resPostman = $this->actingAs($admin, 'admin')->get(route('admin.api-management.docs.postman'));
        $resPostman->assertStatus(200);
        $resPostman->assertJsonStructure([
            'info' => ['name', 'schema'],
            'item',
        ]);

        // 4. Flutter Guide
        $resFlutter = $this->actingAs($admin, 'admin')->get(route('admin.api-management.docs.flutter'));
        $resFlutter->assertStatus(200);
        $resFlutter->assertSee('Flutter Mobile API Integration Guide');
    }
}
