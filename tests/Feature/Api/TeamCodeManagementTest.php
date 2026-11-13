<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

class TeamCodeManagementTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected function admin(): Admin
    {
        return Admin::factory()->create();
    }

    protected function teamPayload(string $code = 'GEM', ?string $username = null): array
    {
        return [
            'team_code' => $code,
            'team_name' => 'Gem Team',
            'username' => $username ?? 'gem-'.bin2hex(random_bytes(3)),
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
            'status' => 'active',
        ];
    }

    // 1. Admin creates a team with a manual team code.
    public function test_admin_creates_team_with_manual_team_code(): void
    {
        $admin = $this->admin();

        $this->postJson('/api/v1/admin/teams', $this->teamPayload('CLA'), $this->actingAsToken($this->adminToken($admin)))
            ->assertCreated()
            ->assertJsonPath('data.team_code', 'CLA')
            ->assertJsonPath('data.team_name', 'Gem Team');

        $this->assertDatabaseHas('teams', ['team_code' => 'CLA']);
    }

    // 2. Duplicate team code is rejected.
    public function test_duplicate_team_code_is_rejected(): void
    {
        $admin = $this->admin();
        Team::factory()->create(['team_code' => 'GEM']);

        $this->postJson('/api/v1/admin/teams', $this->teamPayload('GEM'), $this->actingAsToken($this->adminToken($admin)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_code']);
    }

    // Team code must match the defined format (uppercase letters or TEAM+n).
    public function test_team_code_must_match_defined_format(): void
    {
        $admin = $this->admin();

        foreach (['A', 'lower', 'CLA-1', '1ABC', 'GEMMINE', 'TEAM12345'] as $bad) {
            $this->postJson('/api/v1/admin/teams', $this->teamPayload($bad), $this->actingAsToken($this->adminToken($admin)))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['team_code']);
        }
    }

    public function test_valid_team_code_formats_are_accepted(): void
    {
        $admin = $this->admin();

        foreach (['CLA', 'GEM', 'GPT', 'AB', 'ABCDE', 'ABCDEF', 'TEAM01', 'TEAM1234'] as $good) {
            $this->postJson('/api/v1/admin/teams', $this->teamPayload($good), $this->actingAsToken($this->adminToken($admin)))
                ->assertCreated();
        }
    }

    // 8. Team code cannot be changed after orders are generated.
    public function test_team_code_is_immutable_after_first_order(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $employee = User::factory()->create(['team_id' => $team->id]);

        SalesOrder::factory()->create([
            'team_id' => $team->id,
            'team_code_snapshot' => 'GEM',
            'order_number' => 'GEM-2026-000001',
            'assigned_employee_id' => $employee->id,
        ]);

        $this->putJson("/api/v1/admin/teams/{$team->id}", ['team_code' => 'NEW'], $this->actingAsToken($this->adminToken($admin)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_code']);

        // The stored code is unchanged.
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'team_code' => 'GEM']);
    }

    public function test_team_code_may_change_before_any_orders(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create(['team_code' => 'GEM']);

        $this->putJson("/api/v1/admin/teams/{$team->id}", ['team_code' => 'GPT'], $this->actingAsToken($this->adminToken($admin)))
            ->assertOk();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'team_code' => 'GPT']);
    }

    // 7. Changing team name does not alter existing order ids.
    public function test_team_name_change_does_not_alter_order_numbers(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create(['team_code' => 'GEM']);

        SalesOrder::factory()->create([
            'team_id' => $team->id,
            'team_code_snapshot' => 'GEM',
            'order_number' => 'GEM-2026-000001',
        ]);

        $this->putJson("/api/v1/admin/teams/{$team->id}", ['team_name' => 'Renamed Team'], $this->actingAsToken($this->adminToken($admin)))
            ->assertOk();

        $this->assertDatabaseHas('sales_orders', [
            'team_id' => $team->id,
            'order_number' => 'GEM-2026-000001',
        ]);
    }

    // 7 (module 1). Only an admin may manage team codes.
    public function test_non_admin_cannot_manage_team_codes(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $otherTeam = Team::factory()->create(['team_code' => 'GPT']);

        $this->postJson('/api/v1/admin/teams', $this->teamPayload('XYZ'), $this->actingAsToken($this->teamToken($otherTeam)))
            ->assertForbidden();

        $this->putJson("/api/v1/admin/teams/{$team->id}", ['team_name' => 'x'], $this->actingAsToken($this->teamToken($otherTeam)))
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_create_team(): void
    {
        $this->postJson('/api/v1/admin/teams', $this->teamPayload('CLA'))
            ->assertUnauthorized();
    }
}
