<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    public function test_team_creates_orders_only_within_its_own_team(): void
    {
        $team = Team::factory()->create(['team_code' => 'CLA']);
        $otherTeam = Team::factory()->create(['team_code' => 'GEM']);

        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ada',
            'product_value' => 100.5,
        ], $this->actingAsToken($this->teamToken($team)));

        $response->assertCreated()
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.team_code_snapshot', 'CLA');

        $orderNumber = $response->json('data.order_number');
        $this->assertMatchesRegularExpression('#^CLA-\d{4}-\d{6}$#', $orderNumber);

        // A team cannot create an order for a different team.
        $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ada',
            'team_id' => $otherTeam->id,
        ], $this->actingAsToken($this->teamToken($team)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_employee_creates_orders_under_their_assigned_team(): void
    {
        $team = Team::factory()->create(['team_code' => 'GPT']);
        $employee = User::factory()->create(['team_id' => $team->id]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_name' => 'Grace',
        ], $this->actingAsToken($this->employeeToken($employee)));

        $response->assertCreated()
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.assigned_employee_id', $employee->id);
    }

    public function test_employee_not_assigned_to_a_team_cannot_create_orders(): void
    {
        $employee = User::factory()->create(['team_id' => null]);

        $this->postJson('/api/v1/orders', ['customer_name' => 'Grace'], $this->actingAsToken($this->employeeToken($employee)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_admin_creates_orders_for_a_specific_team(): void
    {
        $admin = Admin::factory()->create();
        $team = Team::factory()->create(['team_code' => 'CLA']);

        $this->postJson('/api/v1/orders', [
            'customer_name' => 'Ada',
            'team_id' => $team->id,
        ], $this->actingAsToken($this->adminToken($admin)))
            ->assertCreated()
            ->assertJsonPath('data.team_id', $team->id);

        // Admins must name a team.
        $this->postJson('/api/v1/orders', ['customer_name' => 'Ada'], $this->actingAsToken($this->adminToken($admin)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_order_cannot_become_active_until_payment_is_verified(): void
    {
        $team = Team::factory()->create(['team_code' => 'GEM']);
        $employee = User::factory()->create(['team_id' => $team->id]);

        // Unpaid order.
        $pending = \App\Models\SalesOrder::factory()->create([
            'team_id' => $team->id,
            'payment_status' => 'pending',
        ]);

        $this->putJson("/api/v1/orders/{$pending->id}/activate", [], $this->actingAsToken($this->employeeToken($employee)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_status']);

        $this->assertDatabaseMissing('subscriptions', ['order_id' => $pending->id, 'status' => 'active']);

        // Paid order activates cleanly.
        $paid = \App\Models\SalesOrder::factory()->create([
            'team_id' => $team->id,
            'payment_status' => 'paid',
        ]);

        $this->putJson("/api/v1/orders/{$paid->id}/activate", [], $this->actingAsToken($this->employeeToken($employee)))
            ->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'order_id' => $paid->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('sales_orders', [
            'id' => $paid->id,
            'order_status' => 'active',
        ]);
    }
}
