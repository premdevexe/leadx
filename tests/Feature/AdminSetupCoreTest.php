<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSetupCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_admin_setup_core_page(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.setup.core'));

        $response->assertStatus(200);
        $response->assertSee('System Installation and Recovery Diagnostics');
        $response->assertSee('Maintenance and Recovery Actions');
        $response->assertSee('Apply Pending Migrations');
        $response->assertSee('Connect Storage Link');
    }

    public function test_employee_cannot_access_admin_setup_core(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAs($employee, 'employee')->get(route('admin.setup.core'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_unauthenticated_user_cannot_access_admin_setup_core(): void
    {
        $response = $this->get(route('admin.setup.core'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_super_admin_can_run_core_actions(): void
    {
        $admin = Admin::factory()->create();

        // Test clear cache action
        $clearCacheResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.setup.core.action'), ['action' => 'clear_cache']);
        $clearCacheResponse->assertRedirect();
        $clearCacheResponse->assertSessionHas('status');

        // Test DB connection action
        $testDbResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.setup.core.action'), ['action' => 'test_db']);
        $testDbResponse->assertRedirect();
        $this->assertTrue(
            session()->has('status') || session()->has('error'),
            'Expected status or error message in session'
        );

        // Test storage link action
        $storageLinkResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.setup.core.action'), ['action' => 'storage_link']);
        $storageLinkResponse->assertRedirect();
        $storageLinkResponse->assertSessionHas('status');
    }
}
