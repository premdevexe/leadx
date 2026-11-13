<?php

namespace Tests\Feature\Api;

use App\Models\EmployeeGoogleConnection;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

class GoogleCalendarOauthTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected User $employee;
    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = User::factory()->create();
        $this->team = Team::factory()->create();

        Config::set('google.client_id', 'test-client-id');
        Config::set('google.client_secret', 'test-client-secret');
        Config::set('google.redirect_uri', 'http://localhost:8000/api/v1/google/calendar/callback');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.fake-access-token',
                'refresh_token' => '1//fake-refresh-token',
                'expires_in' => 3599,
                'email' => 'employee@gmail.com',
            ], 200),
            'googleapis.com/*' => Http::response([], 200),
        ]);
    }

    public function test_connect_endpoint_returns_url_and_state_for_an_employee(): void
    {
        $response = $this->getJson('/api/v1/employee/google/calendar/connect', $this->actingAsToken($this->employeeToken($this->employee)));

        $response->assertOk()
            ->assertJsonStructure(['url', 'state', 'redirect_uri'])
            ->assertJsonPath('redirect_uri', 'http://localhost:8000/api/v1/google/calendar/callback');

        // The URL carries the client id and a state param; no secret anywhere.
        $url = $response->json('url');
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('state='.$response->json('state'), $url);
        $this->assertStringNotContainsString('test-client-secret', $url);
    }

    public function test_a_team_token_cannot_start_a_personal_calendar_connection(): void
    {
        $this->getJson('/api/v1/employee/google/calendar/connect', $this->actingAsToken($this->teamToken($this->team)))
            ->assertStatus(403);
    }

    public function test_callback_completes_the_flow_and_persists_encrypted_tokens(): void
    {
        // Start the flow (caches a state bound to the employee).
        $connect = $this->getJson('/api/v1/employee/google/calendar/connect', $this->actingAsToken($this->employeeToken($this->employee)))->assertOk();
        $state = $connect->json('state');

        // Simulate Google's browser redirect (no Bearer token on this route).
        $response = $this->getJson("/api/v1/google/calendar/callback?code=4/abc&state={$state}");

        $response->assertOk()
            ->assertJsonPath('employee_id', $this->employee->id)
            ->assertJsonPath('data.connection.status', 'connected')
            ->assertJsonPath('data.connection.google_email', 'employee@gmail.com')
            // Module 2: credentials are never serialized.
            ->assertJsonMissingPath('data.connection.access_token')
            ->assertJsonMissingPath('data.connection.refresh_token');

        $this->assertDatabaseHas('employee_google_connections', [
            'employee_id' => $this->employee->id,
            'status' => 'connected',
            'google_email' => 'employee@gmail.com',
        ]);

        // Tokens are encrypted at rest: the raw column value must not
        // contain the plaintext.
        $raw = \Illuminate\Support\Facades\DB::table('employee_google_connections')->where('employee_id', $this->employee->id)->first();
        $this->assertNotSame('ya29.fake-access-token', $raw->access_token);
        $this->assertStringContainsString('eyJ', $raw->access_token); // encrypted: prefixed JSON
    }

    public function test_state_is_single_use_and_required(): void
    {
        $connect = $this->getJson('/api/v1/employee/google/calendar/connect', $this->actingAsToken($this->employeeToken($this->employee)))->assertOk();
        $state = $connect->json('state');

        $this->getJson("/api/v1/google/calendar/callback?code=4/abc&state={$state}")->assertOk();

        // Replay with the same state must fail (CSRF / replay protection).
        $this->getJson("/api/v1/google/calendar/callback?code=4/abc&state={$state}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['state']);

        // A missing state must also fail.
        $this->getJson('/api/v1/google/calendar/callback?code=4/abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['state']);
    }

    public function test_status_endpoint_never_returns_credentials(): void
    {
        // Seed a connection with tokens, then read it back via the API.
        $connection = $this->createConnection();

        $this->getJson('/api/v1/employee/google/calendar', $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonMissingPath('data.connection.access_token')
            ->assertJsonMissingPath('data.connection.refresh_token');
    }

    public function test_disconnect_clears_credentials(): void
    {
        $this->createConnection();

        $this->postJson('/api/v1/employee/google/calendar/disconnect', [], $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertOk()
            ->assertJsonPath('data.connection.status', 'disconnected');

        $this->assertDatabaseHas('employee_google_connections', [
            'employee_id' => $this->employee->id,
            'status' => 'disconnected',
        ]);
    }

    public function test_missing_credentials_yield_a_503_not_a_crash(): void
    {
        Config::set('google.client_id', '');

        $this->getJson('/api/v1/employee/google/calendar/connect', $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertStatus(503);
    }

    public function test_callback_reports_google_errors_cleanly(): void
    {
        $this->getJson('/api/v1/google/calendar/callback?error=access_denied')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Google returned an error: access_denied');
    }

    protected function createConnection(): EmployeeGoogleConnection
    {
        $connection = $this->employee->googleCalendarConnection()->create([
            'google_email' => 'employee@gmail.com',
            'access_token' => 'ya29.stored-access-token',
            'refresh_token' => '1//stored-refresh-token',
            'access_token_expires_at' => now()->addHours(1),
            'status' => 'connected',
            'connected_at' => now(),
        ]);

        return $connection->fresh();
    }
}
