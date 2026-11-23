<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AppNotification;
use App\Models\Team;
use App\Models\User;
use App\Services\FcmService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AppNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'admin@leadx.com',
            'password' => bcrypt('password123'),
            'role' => 'super_admin',
            'is_super_admin' => true,
        ]);
    }

    public function test_guest_is_redirected_from_app_notifications(): void
    {
        $response = $this->get(route('admin.notifications.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_employee_cannot_access_admin_app_notifications(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'role' => 'employee']);

        $response = $this->actingAs($employee, 'employee')->get(route('admin.notifications.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_app_notifications_dashboard(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.notifications.index'));
        $response->assertStatus(200);
        $response->assertSee('Mobile Push Notifications');
        $response->assertSee('Compose Push Notification');
        $response->assertSee('Mobile Tray Preview');
    }

    public function test_send_push_notification_validation_fails_on_empty_input(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.notifications.send'), []);
        $response->assertSessionHasErrors(['title', 'body', 'target']);
    }

    public function test_send_push_notification_to_general_interest_success(): void
    {
        // Mock PushNotificationService. send() now calls isConfigured() before
        // delivery, so stub it; keep FcmService off so no real device-token
        // query / FCM send runs during the test.
        $mockService = Mockery::mock(PushNotificationService::class);
        $mockService->shouldReceive('isConfigured')->andReturn(true);
        $mockService->shouldReceive('sendToInterests')
            ->once()
            ->with(
                ['hello'],
                'Test Title',
                'Test Body',
                ['action' => 'orders', 'title' => 'Test Title', 'body' => 'Test Body'],
            )
            ->andReturn('pub_test_12345');

        $fcmMock = Mockery::mock(FcmService::class);
        $fcmMock->shouldReceive('isConfigured')->andReturn(false);

        $this->app->instance(PushNotificationService::class, $mockService);
        $this->app->instance(FcmService::class, $fcmMock);

        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.notifications.send'), [
            'title' => 'Test Title',
            'body' => 'Test Body',
            'target' => 'hello',
            'action_route' => 'orders',
        ]);

        $response->assertRedirect(route('admin.notifications.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('app_notifications', [
            'title' => 'Test Title',
            'body' => 'Test Body',
            'target' => 'hello',
            'publish_id' => 'pub_test_12345',
            'action_url' => 'orders',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_send_push_notification_to_specific_employee_interest(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'role' => 'employee']);

        $mockService = Mockery::mock(PushNotificationService::class);
        $mockService->shouldReceive('isConfigured')->andReturn(true);
        $mockService->shouldReceive('sendToInterests')
            ->once()
            ->with(
                ["user_{$employee->id}"],
                'Direct Alert',
                'Direct Message',
                ['action' => 'customers', 'title' => 'Direct Alert', 'body' => 'Direct Message'],
            )
            ->andReturn('pub_user_999');

        $fcmMock = Mockery::mock(FcmService::class);
        $fcmMock->shouldReceive('isConfigured')->andReturn(false);

        $this->app->instance(PushNotificationService::class, $mockService);
        $this->app->instance(FcmService::class, $fcmMock);

        $response = $this->actingAs($this->admin, 'admin')->post(route('admin.notifications.send'), [
            'title' => 'Direct Alert',
            'body' => 'Direct Message',
            'target' => 'user',
            'user_id' => $employee->id,
            'action_route' => 'customers',
        ]);

        $response->assertRedirect(route('admin.notifications.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('app_notifications', [
            'title' => 'Direct Alert',
            'target' => 'user',
            'target_value' => (string) $employee->id,
            'publish_id' => 'pub_user_999',
            'action_url' => 'customers',
        ]);
    }

    public function test_notification_history_displays_sent_records(): void
    {
        AppNotification::create([
            'title' => 'Broadcast Order',
            'body' => 'A new broadcast order was created',
            'target' => 'all',
            'target_value' => null,
            'action_url' => 'home',
            'publish_id' => 'pub_all_888',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.notifications.index'));
        $response->assertStatus(200);
        $response->assertSee('Broadcast Order');
        $response->assertSee('pub_all_888');
        $response->assertSee($this->admin->name);
    }
}
