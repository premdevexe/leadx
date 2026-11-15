<?php

namespace Tests\Feature\WebPortal;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPortalTest extends TestCase
{
    use RefreshDatabase;

    private function createTeamWithActiveProduct(): array
    {
        $team = Team::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);

        return [$team, $product];
    }

    private function actingAsTeam(Team $team): self
    {
        $this->withSession(['team_id' => $team->id]);

        return $this;
    }

    public function test_team_login_page_redirects_to_employee_login(): void
    {
        $response = $this->get('/team/login');

        $response->assertRedirect(route('employee.login'));
        $response->assertSessionHas('info');
    }

    public function test_team_login_post_redirects_to_employee_login(): void
    {
        $team = Team::factory()->create(['password' => bcrypt('password')]);

        $response = $this->post('/team/login', [
            'login' => $team->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('employee.login'));
        $response->assertSessionHas('info');
    }

    public function test_unauthenticated_team_is_redirected_to_login(): void
    {
        $response = $this->get('/team/dashboard');

        $response->assertRedirect(route('employee.login'));
    }

    public function test_inactive_team_cannot_login(): void
    {
        $team = Team::factory()->create([
            'password' => bcrypt('password'),
            'status' => 'inactive',
        ]);

        $response = $this->post('/team/login', [
            'login' => $team->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('employee.login'));
    }

    public function test_team_dashboard_shows_real_statistics(): void
    {
        [$team, $product] = $this->createTeamWithActiveProduct();

        SalesOrder::factory()->count(3)->create([
            'team_id' => $team->id,
            'product_id' => $product->id,
        ]);

        $this->actingAsTeam($team);

        $response = $this->get('/team/dashboard');

        $response->assertStatus(200);
        $response->assertSee('3'); // Total orders count
    }

    public function test_team_orders_index_shows_only_team_orders(): void
    {
        [$teamA, $productA] = $this->createTeamWithActiveProduct();
        [$teamB, $productB] = $this->createTeamWithActiveProduct();

        $orderA = SalesOrder::factory()->create(['team_id' => $teamA->id, 'product_id' => $productA->id]);
        SalesOrder::factory()->create(['team_id' => $teamB->id, 'product_id' => $productB->id]);

        $this->actingAsTeam($teamA);

        $response = $this->get('/team/orders');

        $response->assertStatus(200);
        $response->assertSee($orderA->order_number);
        $response->assertDontSee(SalesOrder::where('team_id', $teamB->id)->first()->order_number);
    }

    public function test_team_cannot_access_another_teams_order(): void
    {
        [$teamA] = $this->createTeamWithActiveProduct();
        [$teamB, $productB] = $this->createTeamWithActiveProduct();

        $orderB = SalesOrder::factory()->create(['team_id' => $teamB->id, 'product_id' => $productB->id]);

        $this->actingAsTeam($teamA);

        $response = $this->get("/team/orders/{$orderB->id}");

        $response->assertStatus(403);
    }

    public function test_team_can_create_order_with_active_product(): void
    {
        [$team, $product] = $this->createTeamWithActiveProduct();

        $this->actingAsTeam($team);

        $response = $this->post('/team/orders', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '+15551234567',
            'customer_email' => 'test@example.com',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => '100.00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales_orders', [
            'team_id' => $team->id,
            'customer_name' => 'Test Customer',
        ]);
    }

    public function test_team_order_uses_authoritative_product_data(): void
    {
        [$team, $product] = $this->createTeamWithActiveProduct();

        $this->actingAsTeam($team);

        $this->post('/team/orders', [
            'customer_name' => 'Price Test',
            'customer_phone' => '+15551234567',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => '100.00',
            // Intentionally submitting a different price — must be ignored
            'product_value' => '999.00',
        ]);

        $order = SalesOrder::where('customer_name', 'Price Test')->first();
        $this->assertNotNull($order);
        $this->assertEquals((string) $product->price, (string) $order->product_price_snapshot);
        $this->assertEquals((string) $product->duration, (string) $order->product_duration_snapshot);
    }

    public function test_team_order_number_uses_team_code_and_year_sequence(): void
    {
        [$team, $product] = $this->createTeamWithActiveProduct();

        $this->actingAsTeam($team);

        $this->post('/team/orders', [
            'customer_name' => 'Seq Test',
            'customer_phone' => '+15551234567',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => '100.00',
        ]);

        $order = SalesOrder::where('customer_name', 'Seq Test')->first();
        $this->assertNotNull($order);
        $this->assertStringStartsWith($team->team_code, $order->order_number);
        $this->assertStringContainsString(now()->format('Y'), $order->order_number);
    }

    public function test_team_cannot_submit_arbitrary_team_id(): void
    {
        [$teamA, $productA] = $this->createTeamWithActiveProduct();
        [$teamB] = $this->createTeamWithActiveProduct();

        $this->actingAsTeam($teamA);

        $response = $this->post('/team/orders', [
            'customer_name' => 'Team Test',
            'customer_phone' => '+15551234567',
            'product_id' => $productA->id,
            'duration' => '1y',
            'closing_value' => '100.00',
            // Malicious team_id override attempt
            'team_id' => $teamB->id,
        ]);

        $order = SalesOrder::where('customer_name', 'Team Test')->first();
        $this->assertNotNull($order);
        $this->assertEquals($teamA->id, (int) $order->team_id);
    }

    public function test_team_cannot_select_inactive_product(): void
    {
        [$team] = $this->createTeamWithActiveProduct();
        $inactiveProduct = Product::factory()->create(['status' => 'inactive']);

        $this->actingAsTeam($team);

        $response = $this->post('/team/orders', [
            'customer_name' => 'Inactive Test',
            'customer_phone' => '+15551234567',
            'product_id' => $inactiveProduct->id,
            'duration' => '1y',
            'closing_value' => '100.00',
        ]);

        $response->assertSessionHasErrors('product_id');
    }

    public function test_team_customers_page_renders(): void
    {
        [$team, $product] = $this->createTeamWithActiveProduct();

        SalesOrder::factory()->create(['team_id' => $team->id, 'product_id' => $product->id]);

        $this->actingAsTeam($team);

        $response = $this->get('/team/customers');

        $response->assertStatus(200);
    }

    public function test_team_notifications_page_renders(): void
    {
        $team = Team::factory()->create(['password' => bcrypt('password')]);

        $this->actingAsTeam($team);

        $response = $this->get('/team/notifications');
        $response->assertStatus(200);
    }

    public function test_team_can_logout(): void
    {
        $team = Team::factory()->create(['password' => bcrypt('password')]);

        $this->actingAsTeam($team);

        $response = $this->post('/team/logout');

        $response->assertRedirect(route('employee.login'));
        $this->assertNull(session('team_id'));
    }
}

