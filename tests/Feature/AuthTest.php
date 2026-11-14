<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:testuser|127.0.0.1');
        RateLimiter::clear('login:john@example.com|127.0.0.1');
        RateLimiter::clear('admin:admin|127.0.0.1');
        RateLimiter::clear('admin:admin@leadx.local|127.0.0.1');
    }

    public function test_user_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Sign in');
    }

    public function test_user_can_login_using_email(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->post('/login', [
            'login' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_user_can_login_using_username(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->post('/login', [
            'login' => 'testuser',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_user_can_login_with_legacy_email_field(): void
    {
        $user = User::factory()->create([
            'username' => 'testuser',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->post('/login', [
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->from('/login')->post('/login', [
            'login' => 'testuser',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('login');
        $this->assertGuest('web');
    }

    public function test_user_is_throttled_after_too_many_failed_attempts(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'login' => 'testuser',
                'password' => 'wrong-password',
            ]);
        }

        // 6th attempt should be blocked
        $response = $this->from('/login')->post('/login', [
            'login' => 'testuser',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['login']);
        $errors = session('errors')->get('login');
        $this->assertStringContainsString('Too many login attempts', $errors[0]);
    }

    public function test_user_can_access_dashboard_when_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Your account');
    }

    public function test_unauthenticated_user_is_redirected_from_dashboard(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->post('/logout');
        $response->assertRedirect('/login');
        $this->assertGuest('web');
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
        $response->assertSee('Admin');
    }

    public function test_admin_can_login_using_username(): void
    {
        $admin = Admin::create([
            'name' => 'Demo Admin',
            'username' => 'admin',
            'email' => 'admin@leadx.local',
            'password' => 'password123',
        ]);

        $response = $this->post('/admin/login', [
            'login' => 'admin',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_can_login_using_email(): void
    {
        $admin = Admin::create([
            'name' => 'Demo Admin',
            'username' => 'admin',
            'email' => 'admin@leadx.local',
            'password' => 'password123',
        ]);

        $response = $this->post('/admin/login', [
            'login' => 'admin@leadx.local',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_cannot_login_with_invalid_password(): void
    {
        Admin::create([
            'name' => 'Demo Admin',
            'username' => 'admin',
            'email' => 'admin@leadx.local',
            'password' => 'password123',
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'login' => 'admin',
            'password' => 'wrong',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('login');
        $this->assertGuest('admin');
    }

    public function test_admin_can_access_admin_dashboard_when_authenticated(): void
    {
        $admin = Admin::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'super@leadx.local',
            'password' => 'password123',
        ]);

        $response = $this->actingAs($admin, 'admin')->get('/admin/dashboard');
        $response->assertStatus(200);
        $response->assertSee('superadmin');
    }

    public function test_admin_dashboard_renders_when_admin_has_null_created_at(): void
    {
        // Raw inserts (e.g. one-off seed/test scripts) can bypass Eloquent
        // timestamps, leaving created_at NULL. The dashboard view must not
        // crash formatting that column.
        $admin = Admin::create([
            'name' => 'Null Timestamp Admin',
            'username' => 'nulltsadmin',
            'email' => 'nullts@leadx.local',
            'password' => 'password123',
        ]);

        DB::table('admins')->where('id', $admin->id)->update(['created_at' => null]);
        $admin->refresh();

        $response = $this->actingAs($admin, 'admin')->get('/admin/dashboard');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_visitor_cannot_access_admin_dashboard(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_can_logout(): void
    {
        $admin = Admin::create([
            'name' => 'Demo Admin',
            'username' => 'admin',
            'email' => 'admin@leadx.local',
            'password' => 'password123',
        ]);

        $response = $this->actingAs($admin, 'admin')->post('/admin/logout');
        $response->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    }
}
