<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminsManagementFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_admins_index_renders_all_accounts(): void
    {
        $admin = Admin::factory()->create(['username' => 'panel_admin', 'name' => 'Panel Admin']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.admins'))
            ->assertOk()
            ->assertSee('panel_admin')
            ->assertSee(route('admin.admins.edit', $admin));
    }

    public function test_admin_can_create_new_admin(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.admins.store'), [
                'name' => 'Second Admin',
                'username' => 'second_admin',
                'email' => 'second@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])
            ->assertRedirect(route('admin.admins'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('admins', [
            'username' => 'second_admin',
            'email' => 'second@example.com',
        ]);
        $this->assertTrue(Hash::check('secret-password', Admin::where('username', 'second_admin')->first()->password));
    }

    public function test_admin_can_edit_admin_without_password_reset(): void
    {
        $admin = Admin::factory()->create(['username' => 'edit_me', 'name' => 'Before', 'email' => 'edit@example.com']);
        $oldHash = $admin->password;

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.admins.update', $admin), [
                'name' => 'After',
                'username' => 'edit_me',
                'email' => 'edit@example.com',
            ])
            ->assertRedirect(route('admin.admins'))
            ->assertSessionHas('status');

        $admin->refresh();
        $this->assertSame('After', $admin->name);
        // Blank password keeps the old hash.
        $this->assertSame($oldHash, $admin->password);
    }

    public function test_admin_can_reset_admin_password(): void
    {
        $admin = Admin::factory()->create(['username' => 'reset_pw']);
        $oldHash = $admin->password;

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.admins.update', $admin), [
                'name' => $admin->name,
                'username' => 'reset_pw',
                'email' => $admin->email,
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertRedirect(route('admin.admins'));

        $admin->refresh();
        $this->assertNotSame($oldHash, $admin->password);
        $this->assertTrue(Hash::check('new-secret-password', $admin->password));
    }

    public function test_duplicate_username_rejected_on_create_and_edit(): void
    {
        Admin::factory()->create(['username' => 'taken_admin']);

        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.admins.create'))
            ->post(route('admin.admins.store'), [
                'name' => 'Dup',
                'username' => 'taken_admin',
                'email' => 'dup@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])
            ->assertSessionHasErrors('username');

        // Editing a different admin with the same username is also rejected.
        $other = Admin::factory()->create(['username' => 'other_admin']);
        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.admins.edit', $other))
            ->put(route('admin.admins.update', $other), [
                'name' => 'Other',
                'username' => 'taken_admin',
                'email' => 'other@example.com',
            ])
            ->assertSessionHasErrors('username');
    }
}
