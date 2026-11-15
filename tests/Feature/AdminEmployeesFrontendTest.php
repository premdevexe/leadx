<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEmployeesFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_employees_index_renders_delete_button_for_each_employee(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'name' => 'John Doe',
            'username' => 'johndoe',
        ]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.employees'));

        $response->assertOk();
        $response->assertSee('johndoe');
        $response->assertSee(route('admin.employees.destroy', $employee));
        $response->assertSee('Delete');
    }

    public function test_edit_employee_page_renders_delete_button(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'name' => 'Jane Smith',
            'username' => 'janesmith',
        ]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.employees.edit', $employee));

        $response->assertOk();
        $response->assertSee(route('admin.employees.destroy', $employee));
        $response->assertSee('Delete employee');
    }

    public function test_admin_can_delete_employee(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create([
            'role' => 'employee',
            'name' => 'Bob To Delete',
            'username' => 'bobdelete',
            'team_id' => $team->id,
        ]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.employees.destroy', $employee));

        $response->assertRedirect(route('admin.employees'));
        $response->assertSessionHas('status', 'Employee deleted.');

        $this->assertDatabaseMissing('users', [
            'id' => $employee->id,
        ]);
    }

    public function test_unauthenticated_cannot_delete_employee(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $response = $this->delete(route('admin.employees.destroy', $employee));

        $response->assertRedirect(route('admin.login'));
        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
        ]);
    }
}
