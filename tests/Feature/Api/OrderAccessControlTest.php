<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

class OrderAccessControlTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected Team $teamA;

    protected Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teamA = Team::factory()->create(['team_code' => 'GEM']);
        $this->teamB = Team::factory()->create(['team_code' => 'GPT']);
    }

    // 12. A team cannot access another team's orders.
    public function test_team_cannot_access_another_teams_orders(): void
    {
        $foreignOrder = SalesOrder::factory()->create(['team_id' => $this->teamB->id]);
        $token = $this->teamToken($this->teamA);

        $this->getJson("/api/v1/orders/{$foreignOrder->id}", $this->actingAsToken($token))
            ->assertStatus(422);

        $this->getJson("/api/v1/orders/by-number/{$foreignOrder->order_number}", $this->actingAsToken($token))
            ->assertStatus(422);
    }

    public function test_team_can_see_its_own_orders(): void
    {
        $mine = SalesOrder::factory()->create(['team_id' => $this->teamA->id]);

        $this->getJson("/api/v1/orders/{$mine->id}", $this->actingAsToken($this->teamToken($this->teamA)))
            ->assertOk()
            ->assertJsonPath('data.order_number', $mine->order_number);

        $this->getJson('/api/v1/orders', $this->actingAsToken($this->teamToken($this->teamA)))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.team_id', $this->teamA->id);
    }

    // 13. An employee cannot bypass their team's restrictions.
    public function test_employee_cannot_access_orders_outside_their_team(): void
    {
        $employeeA = User::factory()->create(['team_id' => $this->teamA->id]);
        $foreignOrder = SalesOrder::factory()->create(['team_id' => $this->teamB->id]);

        $this->getJson("/api/v1/orders/{$foreignOrder->id}", $this->actingAsToken($this->employeeToken($employeeA)))
            ->assertStatus(422);

        $this->getJson('/api/v1/orders', $this->actingAsToken($this->employeeToken($employeeA)))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_employee_can_access_orders_in_their_team(): void
    {
        $employeeA = User::factory()->create(['team_id' => $this->teamA->id]);
        $ownOrder = SalesOrder::factory()->create(['team_id' => $this->teamA->id, 'assigned_employee_id' => $employeeA->id]);

        $this->getJson("/api/v1/orders/{$ownOrder->id}", $this->actingAsToken($this->employeeToken($employeeA)))
            ->assertOk();

        $this->getJson('/api/v1/orders', $this->actingAsToken($this->employeeToken($employeeA)))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_view_all_orders(): void
    {
        SalesOrder::factory()->create(['team_id' => $this->teamA->id]);
        SalesOrder::factory()->create(['team_id' => $this->teamB->id]);

        $admin = Admin::factory()->create();

        $this->getJson('/api/v1/orders', $this->actingAsToken($this->adminToken($admin)))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_filter_orders_by_team_and_search_by_number(): void
    {
        $first = SalesOrder::factory()->create(['team_id' => $this->teamA->id]);

        $admin = Admin::factory()->create();

        $this->getJson('/api/v1/orders?team_id='.$this->teamB->id, $this->actingAsToken($this->adminToken($admin)))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/orders?order_number='.$first->order_number, $this->actingAsToken($this->adminToken($admin)))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->id);
    }

    public function test_unauthenticated_order_requests_are_rejected(): void
    {
        $order = SalesOrder::factory()->create(['team_id' => $this->teamA->id]);

        $this->getJson("/api/v1/orders/{$order->id}")->assertUnauthorized();
        $this->postJson('/api/v1/orders', ['customer_name' => 'x'])->assertUnauthorized();
    }

    // The service-layer guard behind every endpoint.
    public function test_service_guard_rejects_cross_team_access(): void
    {
        $guard = app(OrderService::class);
        $order = SalesOrder::factory()->create(['team_id' => $this->teamB->id]);

        $this->expectException(ValidationException::class);

        $guard->assertCanAccess($order, $this->teamA);
    }
}
