<?php

namespace Tests\Feature\Api;

use App\Jobs\SendFcmNotificationJob;
use App\Models\EmployeeDeviceToken;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

/**
 * FCM device-token endpoints for the Flutter employee app:
 *
 *   GET    /employee/devices
 *   POST   /employee/devices/register
 *   POST   /employee/devices/token-refresh
 *   DELETE /employee/devices/unregister
 *
 * Ownership rules under test:
 * - an employee can only manage their own devices;
 * - a token claimed by another employee cannot be re-registered;
 * - team/admin tokens are rejected (role gate);
 * - the queued FCM job degrades to a no-op when FCM is not configured,
 *   so notification flows never break without a service account.
 */
class EmployeeFcmDevicesTest extends TestCase
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

    protected function deviceHeaders(): array
    {
        return $this->actingAsToken($this->employeeToken($this->employee));
    }

    public function test_employee_can_register_a_device(): void
    {
        $response = $this->postJson('/api/v1/employee/devices/register', [
            'fcm_token' => 'fcm-token-111',
            'platform' => 'android',
            'device_name' => 'Pixel 8',
        ], $this->deviceHeaders());

        $response->assertCreated();
        $response->assertJsonStructure(['message', 'data' => ['device_id']]);

        $this->assertDatabaseHas('employee_device_tokens', [
            'fcm_token' => 'fcm-token-111',
            'employee_id' => $this->employee->id,
            'is_active' => true,
        ]);
    }

    public function test_registering_without_a_token_is_unauthenticated(): void
    {
        $this->postJson('/api/v1/employee/devices/register', [
            'fcm_token' => 'fcm-token-111',
        ])->assertUnauthorized();
    }

    public function test_registering_a_foreign_tokens_owned_by_another_employee_is_rejected(): void
    {
        EmployeeDeviceToken::create([
            'employee_id' => $this->otherEmployee->id,
            'fcm_token' => 'fcm-token-foreign',
            'platform' => 'android',
        ]);

        $this->postJson('/api/v1/employee/devices/register', [
            'fcm_token' => 'fcm-token-foreign',
        ], $this->deviceHeaders())->assertUnprocessable();

        // Ownership did not change.
        $this->assertSame(
            $this->otherEmployee->id,
            EmployeeDeviceToken::where('fcm_token', 'fcm-token-foreign')->value('employee_id')
        );
    }

    public function test_re_registering_own_token_updates_it_in_place(): void
    {
        EmployeeDeviceToken::create([
            'employee_id' => $this->employee->id,
            'fcm_token' => 'fcm-token-own',
            'platform' => 'android',
        ]);

        $this->postJson('/api/v1/employee/devices/register', [
            'fcm_token' => 'fcm-token-own',
            'device_name' => 'Updated name',
        ], $this->deviceHeaders())->assertCreated();

        $this->assertSame(1, EmployeeDeviceToken::where('fcm_token', 'fcm-token-own')->count());
        $this->assertSame('Updated name', EmployeeDeviceToken::where('fcm_token', 'fcm-token-own')->value('device_name'));
    }

    public function test_device_list_is_scoped_to_the_owner(): void
    {
        EmployeeDeviceToken::create([
            'employee_id' => $this->employee->id,
            'fcm_token' => 'fcm-a',
        ]);
        EmployeeDeviceToken::create([
            'employee_id' => $this->otherEmployee->id,
            'fcm_token' => 'fcm-b',
        ]);

        $this->getJson('/api/v1/employee/devices', $this->deviceHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.fcm_token', 'fcm-a');
    }

    public function test_token_refresh_swaps_the_token_in_place(): void
    {
        EmployeeDeviceToken::create([
            'employee_id' => $this->employee->id,
            'fcm_token' => 'fcm-old',
            'platform' => 'android',
        ]);

        $this->postJson('/api/v1/employee/devices/token-refresh', [
            'old_token' => 'fcm-old',
            'new_token' => 'fcm-new',
        ], $this->deviceHeaders())->assertOk();

        $this->assertDatabaseHas('employee_device_tokens', [
            'fcm_token' => 'fcm-new',
            'employee_id' => $this->employee->id,
        ]);
        $this->assertDatabaseMissing('employee_device_tokens', ['fcm_token' => 'fcm-old']);
    }

    public function test_token_refresh_of_another_employees_token_is_404(): void
    {
        EmployeeDeviceToken::create([
            'employee_id' => $this->otherEmployee->id,
            'fcm_token' => 'fcm-theirs',
        ]);

        $this->postJson('/api/v1/employee/devices/token-refresh', [
            'old_token' => 'fcm-theirs',
            'new_token' => 'fcm-mine',
        ], $this->deviceHeaders())->assertNotFound();
    }

    public function test_unregister_removes_only_the_owners_device(): void
    {
        EmployeeDeviceToken::create([
            'employee_id' => $this->employee->id,
            'fcm_token' => 'fcm-gone',
        ]);
        EmployeeDeviceToken::create([
            'employee_id' => $this->otherEmployee->id,
            'fcm_token' => 'fcm-theirs',
        ]);

        $this->deleteJson('/api/v1/employee/devices/unregister', [
            'fcm_token' => 'fcm-gone',
        ], $this->deviceHeaders())->assertOk();

        $this->assertDatabaseMissing('employee_device_tokens', ['fcm_token' => 'fcm-gone']);
        $this->assertDatabaseHas('employee_device_tokens', ['fcm_token' => 'fcm-theirs']);

        // Unregistering something that is not yours is a 404.
        $this->deleteJson('/api/v1/employee/devices/unregister', [
            'fcm_token' => 'fcm-theirs',
        ], $this->deviceHeaders())->assertNotFound();
    }

    public function test_team_token_cannot_use_employee_device_routes(): void
    {
        $team = \App\Models\Team::factory()->create(['team_code' => 'T2']);

        $this->postJson('/api/v1/employee/devices/register', [
            'fcm_token' => 'fcm-team-attempt',
        ], $this->actingAsToken($this->teamToken($team)))->assertForbidden();
    }

    public function test_fcm_job_is_a_noop_when_not_configured(): void
    {
        EmployeeDeviceToken::create([
            'employee_id' => $this->employee->id,
            'fcm_token' => 'fcm-quiet',
        ]);

        // No service-account file on this box: the job must complete cleanly
        // without throwing, and must not deactivate the token.
        Queue::fake();
        SendFcmNotificationJob::dispatch(
            $this->employee->id,
            'Title',
            'Body',
            ['type' => 'order'],
            'order_created_emp_1'
        );
        Queue::assertPushed(SendFcmNotificationJob::class);

        // Execute it for real (sync) to prove it degrades safely.
        $job = new SendFcmNotificationJob(
            $this->employee->id,
            'Title',
            'Body',
            ['type' => 'order'],
            'order_created_emp_1'
        );
        $job->handle(new \App\Services\FcmService());

        $this->assertTrue(
            EmployeeDeviceToken::where('fcm_token', 'fcm-quiet')->value('is_active'),
            'token must stay active when FCM is unconfigured'
        );
    }

    public function test_deleting_an_employee_cascades_their_tokens(): void
    {
        EmployeeDeviceToken::create([
            'employee_id' => $this->employee->id,
            'fcm_token' => 'fcm-cascade',
        ]);

        $this->employee->delete();

        $this->assertDatabaseMissing('employee_device_tokens', ['fcm_token' => 'fcm-cascade']);
    }
}
