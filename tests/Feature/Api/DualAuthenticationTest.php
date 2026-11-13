<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    // ---------- 1. Super Admin login ----------

    public function test_super_admin_can_login(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'root',
            'password' => 'super-secret',
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'login' => 'root',
            'password' => 'super-secret',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Admin::class,
            'tokenable_id' => $admin->id,
        ]);
    }

    // ---------- 2. Team login ----------

    public function test_team_can_login(): void
    {
        $team = Team::factory()->create([
            'username' => 'sales',
            'password' => 'team-pass',
        ]);

        $response = $this->postJson('/api/v1/team/login', [
            'login' => 'sales',
            'password' => 'team-pass',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Team::class,
            'tokenable_id' => $team->id,
        ]);
    }

    // ---------- 3. Employee login ----------

    public function test_employee_can_login(): void
    {
        $user = User::factory()->create([
            'username' => 'jdoe',
            'email' => 'jdoe@example.com',
            'password' => 'emp-pass',
        ]);

        $response = $this->postJson('/api/v1/employee/login', [
            'login' => 'jdoe',
            'password' => 'emp-pass',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
    }

    // ---------- 4. Invalid credentials ----------

    public function test_invalid_credentials_are_rejected(): void
    {
        Team::factory()->create(['username' => 'sales', 'password' => 'team-pass']);

        $response = $this->postJson('/api/v1/team/login', [
            'login' => 'sales',
            'password' => 'wrong',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    }

    // ---------- 5. Inactive team login rejection ----------

    public function test_inactive_team_cannot_login(): void
    {
        Team::factory()->inactive()->create([
            'username' => 'sales',
            'password' => 'team-pass',
        ]);

        $response = $this->postJson('/api/v1/team/login', [
            'login' => 'sales',
            'password' => 'team-pass',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    }

    // ---------- 6. Inactive employee login rejection ----------

    public function test_inactive_employee_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'username' => 'jdoe',
            'password' => 'emp-pass',
        ]);

        $response = $this->postJson('/api/v1/employee/login', [
            'login' => 'jdoe',
            'password' => 'emp-pass',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    }

    // ---------- 7. Team can view its own members ----------

    public function test_team_can_view_its_members(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create(['team_id' => $team->id]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'joined_at' => now(),
        ]);

        $token = $team->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/team/members');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ---------- 8. Team cannot view another team's members ----------

    public function test_team_cannot_view_another_teams_members(): void
    {
        $other = Team::factory()->create();
        $stranger = User::factory()->create(['team_id' => $other->id]);
        TeamMember::create([
            'team_id' => $other->id,
            'user_id' => $stranger->id,
            'joined_at' => now(),
        ]);

        $team = Team::factory()->create();
        $token = $team->createToken('test')->plainTextToken;

        // The team's own members endpoint returns only its own (empty) list.
        $response = $this->withToken($token)->getJson('/api/v1/team/members');

        $response->assertOk();
        $this->assertSame([], collect($response->json('data'))->all());
    }

    // ---------- 9. Team cannot create or edit members ----------

    public function test_team_cannot_create_or_edit_members(): void
    {
        $team = Team::factory()->create();
        $token = $team->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/admin/teams', ['team_code' => 'X'])
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/v1/admin/teams/'.$team->id.'/members', ['user_id' => 1])
            ->assertForbidden();
    }

    // ---------- 10. Employee cannot access Admin APIs ----------

    public function test_employee_cannot_access_admin_apis(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/teams')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/employees')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/profile')->assertForbidden();
    }

    // ---------- 11. Employee cannot access Team Management APIs ----------

    public function test_employee_cannot_access_team_management_apis(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/team/profile')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/team/members')->assertForbidden();
    }

    // ---------- 12. Admin can create teams ----------

    public function test_admin_can_create_teams(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/admin/teams', [
            'team_code' => 'NEW',
            'team_name' => 'New Team',
            'username' => 'newteam',
            'password' => 'strong-password-1',
            'password_confirmation' => 'strong-password-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.team_code', 'NEW');

        $this->assertDatabaseHas('teams', ['team_code' => 'NEW']);
    }

    // ---------- 13. Admin can create employees ----------

    public function test_admin_can_create_employees(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/admin/employees', [
            'name' => 'New Employee',
            'username' => 'newemp',
            'email' => 'newemp@example.com',
            'password' => 'strong-password-2',
            'password_confirmation' => 'strong-password-2',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.username', 'newemp');

        $this->assertDatabaseHas('users', ['username' => 'newemp']);
    }

    // ---------- 14. Admin can assign employees to teams ----------

    public function test_admin_can_assign_employees_to_teams(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $team = Team::factory()->create();
        $employee = User::factory()->create();

        $response = $this->withToken($token)->postJson('/api/v1/admin/teams/'.$team->id.'/members', [
            'user_id' => $employee->id,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $employee->id,
            'left_at' => null,
        ]);
        $this->assertSame($team->id, $employee->fresh()->team_id);
    }

    // ---------- 15. Logout revokes authentication token ----------

    public function test_logout_revokes_authentication_token(): void
    {
        $team = Team::factory()->create();
        $accessToken = $team->createToken('test');
        $plain = $accessToken->plainTextToken;

        $this->withToken($plain)
            ->postJson('/api/v1/team/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        // The token row must be actually deleted, not merely ignored.
        $this->assertSame(0, $team->tokens()->count());
        $this->assertFalse(
            \Laravel\Sanctum\PersonalAccessToken::where('token', hash('sha256', $plain))->exists()
        );

        // In a real request cycle each call is a fresh process, so the
        // deleted token is unknown. In tests the guard caches the user for
        // the container's lifetime; reset it so the next request resolves
        // the Bearer token through the normal (now-failing) token lookup.
        $this->app->forgetInstance('auth');

        $this->withToken($plain)
            ->getJson('/api/v1/team/profile')
            ->assertUnauthorized();
    }
}
