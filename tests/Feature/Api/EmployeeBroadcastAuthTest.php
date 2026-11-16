<?php

namespace Tests\Feature\Api;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

/**
 * Real-time (Reverb) channel authorization for the Flutter app:
 *
 *   POST /employee/broadcasting/auth   { channel_name, socket_id }
 *
 * Under test the broadcaster is the `null` driver (phpunit.xml), so the
 * endpoint's reachable-200 marker is returned and the OWNERSHIP decision is
 * what's asserted: an employee may authorize only their own
 * `user.{id}.notifications` channel, and team/admin tokens are rejected.
 */
class EmployeeBroadcastAuthTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected Team $team;

    protected User $employee;

    protected User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create(['team_code' => 'GEM']);
        $this->employee = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherEmployee = User::factory()->create(['team_id' => $this->team->id]);
    }

    protected function employeeHeaders(): array
    {
        return $this->actingAsToken($this->employeeToken($this->employee));
    }

    public function test_employee_cannot_authenticate_without_a_token(): void
    {
        $this->postJson('/api/v1/employee/broadcasting/auth', [
            'channel_name' => 'private-user.'.$this->employee->id.'.notifications',
            'socket_id' => '123.456',
        ])->assertUnauthorized();
    }

    public function test_employee_may_authorize_their_own_channel(): void
    {
        $this->postJson('/api/v1/employee/broadcasting/auth', [
            'channel_name' => 'private-user.'.$this->employee->id.'.notifications',
            'socket_id' => '123.456',
        ], $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('authorized', true);
    }

    public function test_employee_cannot_authorize_another_employees_channel(): void
    {
        $this->postJson('/api/v1/employee/broadcasting/auth', [
            'channel_name' => 'private-user.'.$this->otherEmployee->id.'.notifications',
            'socket_id' => '123.456',
        ], $this->employeeHeaders())->assertForbidden();
    }

    public function test_employee_cannot_authorize_a_non_notification_channel(): void
    {
        $this->postJson('/api/v1/employee/broadcasting/auth', [
            'channel_name' => 'private-user.'.$this->employee->id.'.orders',
            'socket_id' => '123.456',
        ], $this->employeeHeaders())->assertForbidden();
    }

    public function test_team_token_is_rejected_on_employee_broadcast_auth(): void
    {
        $team = Team::factory()->create(['team_code' => 'T3']);

        $this->postJson('/api/v1/employee/broadcasting/auth', [
            'channel_name' => 'private-user.'.$this->employee->id.'.notifications',
            'socket_id' => '123.456',
        ], $this->actingAsToken($this->teamToken($team)))->assertForbidden();
    }

    public function test_unknown_channel_shape_is_denied(): void
    {
        $this->postJson('/api/v1/employee/broadcasting/auth', [
            'channel_name' => 'private-random-channel',
            'socket_id' => '123.456',
        ], $this->employeeHeaders())->assertForbidden();
    }
}
