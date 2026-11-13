<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

class FollowUpManagementTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected Team $team;
    protected Customer $customer;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->customer = Customer::factory()->paying()->create(['team_id' => $this->team->id]);
        $this->employee = User::factory()->create(['team_id' => $this->team->id]);
    }

    public function test_employee_creates_a_follow_up_for_a_verified_customer(): void
    {
        $response = $this->postJson('/api/v1/follow-ups', [
            'customer_id' => $this->customer->id,
            'title' => 'Quarterly check-in',
            'follow_up_type' => 'retention',
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
            'priority' => 'high',
        ], $this->actingAsToken($this->employeeToken($this->employee)));

        $response->assertCreated();
        $response->assertJsonPath('data.customer_id', $this->customer->id);
        $response->assertJsonPath('data.team_id', $this->team->id);
        $response->assertJsonPath('data.assigned_employee_id', $this->employee->id);
        $response->assertJsonPath('data.status', 'scheduled');
        $response->assertJsonPath('data.calendar_sync_status', 'not_synced');

        // A history row is written on creation.
        $this->assertDatabaseHas('follow_up_histories', [
            'follow_up_id' => $response->json('data.id'),
            'status' => 'scheduled',
        ]);
    }

    public function test_follow_up_requires_a_verified_customer(): void
    {
        $enquiry = Customer::factory()->enquiry()->create(['team_id' => $this->team->id]);

        $this->postJson('/api/v1/follow-ups', [
            'customer_id' => $enquiry->id,
            'title' => 'Nope',
        ], $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_employee_cannot_target_a_foreign_team_customer(): void
    {
        $otherTeam = Team::factory()->create();
        $foreignCustomer = Customer::factory()->paying()->create(['team_id' => $otherTeam->id]);

        $this->postJson('/api/v1/follow-ups', [
            'customer_id' => $foreignCustomer->id,
            'title' => 'Out of reach',
        ], $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_admin_can_view_all_follow_ups(): void
    {
        FollowUp::factory()->create([
            'customer_id' => $this->customer->id,
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'title' => 'Team follow-up',
        ]);

        // A foreign follow-up outside this team.
        $foreignTeam = Team::factory()->create();
        $foreignCustomer = Customer::factory()->paying()->create(['team_id' => $foreignTeam->id]);
        FollowUp::factory()->create([
            'customer_id' => $foreignCustomer->id,
            'team_id' => $foreignTeam->id,
            'title' => 'Foreign follow-up',
        ]);

        $admin = Admin::factory()->create();

        $this->getJson('/api/v1/follow-ups', $this->actingAsToken($this->adminToken($admin)))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_team_sees_only_its_own_follow_ups(): void
    {
        FollowUp::factory()->create([
            'customer_id' => $this->customer->id,
            'team_id' => $this->team->id,
            'title' => 'Mine',
        ]);

        $foreignTeam = Team::factory()->create();
        $foreignCustomer = Customer::factory()->paying()->create(['team_id' => $foreignTeam->id]);
        FollowUp::factory()->create([
            'customer_id' => $foreignCustomer->id,
            'team_id' => $foreignTeam->id,
            'title' => 'Not mine',
        ]);

        $this->getJson('/api/v1/follow-ups', $this->actingAsToken($this->teamToken($this->team)))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_employee_cannot_manage_another_employee_follow_up(): void
    {
        $otherEmployee = User::factory()->create(['team_id' => $this->team->id]);
        $followUp = FollowUp::factory()->create([
            'customer_id' => $this->customer->id,
            'team_id' => $this->team->id,
            'assigned_employee_id' => $otherEmployee->id,
        ]);

        // Not their own — a same-team employee cannot manage it.
        $this->postJson("/api/v1/follow-ups/{$followUp->id}/complete", [
            'outcome' => 'done',
        ], $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertStatus(422);

        // But the assigned employee can.
        $this->postJson("/api/v1/follow-ups/{$followUp->id}/complete", [
            'outcome' => 'Renewed',
            'notes' => 'Customer happy',
        ], $this->actingAsToken($this->employeeToken($otherEmployee)))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('follow_up_histories', [
            'follow_up_id' => $followUp->id,
            'status' => 'completed',
            'outcome' => 'Renewed',
        ]);
    }

    public function test_reschedule_requires_a_new_time(): void
    {
        $followUp = FollowUp::factory()->create([
            'customer_id' => $this->customer->id,
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
        ]);

        $this->postJson("/api/v1/follow-ups/{$followUp->id}/reschedule", [], $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);

        $this->postJson("/api/v1/follow-ups/{$followUp->id}/reschedule", [
            'scheduled_at' => now()->addWeek()->toDateTimeString(),
        ], $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertOk()
            ->assertJsonPath('data.status', 'rescheduled');
    }

    public function test_completed_follow_up_cannot_be_cancelled(): void
    {
        $followUp = FollowUp::factory()->create([
            'customer_id' => $this->customer->id,
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'status' => 'completed',
        ]);

        $this->postJson("/api/v1/follow-ups/{$followUp->id}/cancel", [], $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertStatus(422);
    }

    public function test_follow_up_history_endpoint(): void
    {
        $followUp = FollowUp::factory()->create([
            'customer_id' => $this->customer->id,
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
        ]);

        $this->postJson("/api/v1/follow-ups/{$followUp->id}/complete", [
            'outcome' => 'Closed',
        ], $this->actingAsToken($this->employeeToken($this->employee)))->assertOk();

        $this->getJson("/api/v1/follow-ups/{$followUp->id}/history", $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertOk()
            // The endpoint returns a plain response()->json() — no top-level
            // `data` wrapper; the history collection carries its own `data`.
            ->assertJsonCount(1, 'history.data');
    }

    public function test_verified_customers_endpoint_scopes_to_team(): void
    {
        $foreignTeam = Team::factory()->create();
        Customer::factory()->paying()->create(['team_id' => $foreignTeam->id]);

        $this->getJson('/api/v1/customers/verified', $this->actingAsToken($this->teamToken($this->team)))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
