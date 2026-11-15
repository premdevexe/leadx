<?php

namespace Tests\Feature\WebPortal;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePortalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsEmployee(User $employee)
    {
        $this->withSession(['employee_id' => $employee->id]);
        $this->app['request']->setUserResolver(fn () => $employee);

        return $this;
    }

    public function test_employee_login_page_renders(): void
    {
        $response = $this->get('/employee/login');

        $response->assertStatus(200);
        $response->assertSee('Employee Login');
    }

    public function test_non_employee_cannot_login_to_employee_portal(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password'), 'role' => 'admin']);

        $response = $this->post('/employee/login', [
            'login' => $user->username,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_employee_can_login_and_see_dashboard(): void
    {
        $employee = User::factory()->create([
            'password' => bcrypt('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $response = $this->post('/employee/login', [
            'login' => $employee->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('employee.dashboard'));

        $response = $this->get('/employee/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Employee Dashboard');
    }

    public function test_unauthenticated_employee_is_redirected_to_login(): void
    {
        $response = $this->get('/employee/dashboard');

        $response->assertRedirect(route('employee.login'));
    }

    public function test_inactive_employee_cannot_login(): void
    {
        $employee = User::factory()->create([
            'password' => bcrypt('password'),
            'role' => 'employee',
            'status' => 'inactive',
        ]);

        $response = $this->post('/employee/login', [
            'login' => $employee->username,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors();
    }

    public function test_employee_dashboard_shows_personal_statistics(): void
    {
        $team = Team::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $team->id,
        ]);

        SalesOrder::factory()->count(2)->create([
            'assigned_employee_id' => $employee->id,
            'team_id' => $team->id,
            'product_id' => $product->id,
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/dashboard');

        $response->assertStatus(200);
        $response->assertSee('2');
    }

    public function test_employee_orders_index_shows_only_own_orders(): void
    {
        $team = Team::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);
        $employeeA = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $team->id,
        ]);
        $employeeB = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $team->id,
        ]);

        $orderA = SalesOrder::factory()->create([
            'assigned_employee_id' => $employeeA->id,
            'team_id' => $team->id,
            'product_id' => $product->id,
        ]);
        $orderB = SalesOrder::factory()->create([
            'assigned_employee_id' => $employeeB->id,
            'team_id' => $team->id,
            'product_id' => $product->id,
        ]);

        $this->actingAsEmployee($employeeA);

        $response = $this->get('/employee/orders');

        $response->assertStatus(200);
        $response->assertSee($orderA->order_number);
        $response->assertDontSee($orderB->order_number);
    }

    public function test_employee_cannot_access_another_employees_order(): void
    {
        $team = Team::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);
        $employeeA = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $team->id,
        ]);
        $employeeB = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $team->id,
        ]);

        $orderB = SalesOrder::factory()->create([
            'assigned_employee_id' => $employeeB->id,
            'team_id' => $team->id,
            'product_id' => $product->id,
        ]);

        $this->actingAsEmployee($employeeA);

        $response = $this->get("/employee/orders/{$orderB->id}");

        $response->assertStatus(403);
    }

    public function test_employee_can_create_order_with_active_product(): void
    {
        $team = Team::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $team->id,
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->post('/employee/orders', [
            'customer_name' => 'Employee Customer',
            'customer_phone' => '+15559876543',
            'customer_email' => 'emp@example.com',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => '200.00',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales_orders', [
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'customer_name' => 'Employee Customer',
        ]);
    }

    public function test_employee_without_team_cannot_create_order(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => null,
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/orders/create');

        $response->assertSessionHasErrors();
    }

    public function test_employee_cannot_access_admin_pages(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/admin/dashboard');

        $response->assertStatus(302); // Redirect away from admin
    }

    public function test_employee_cannot_access_team_management(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/admin/teams');

        $response->assertStatus(302);
    }

    public function test_employee_order_has_correct_attribution(): void
    {
        $team = Team::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $team->id,
        ]);

        $this->actingAsEmployee($employee);

        $this->post('/employee/orders', [
            'customer_name' => 'Attr Test',
            'customer_phone' => '+15551234567',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => '150.00',
        ]);

        $order = SalesOrder::where('customer_name', 'Attr Test')->first();
        $this->assertNotNull($order);
        $this->assertEquals($team->id, (int) $order->team_id);
        $this->assertEquals($employee->id, (int) $order->assigned_employee_id);
    }

    public function test_employee_order_uses_authoritative_product_data(): void
    {
        $team = Team::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $team->id,
        ]);

        $this->actingAsEmployee($employee);

        $this->post('/employee/orders', [
            'customer_name' => 'Auth Price',
            'customer_phone' => '+15551234567',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => '100.00',
            // Ignored: product value comes from DB
            'product_value' => '0.00',
        ]);

        $order = SalesOrder::where('customer_name', 'Auth Price')->first();
        $this->assertNotNull($order);
        $this->assertEquals((string) $product->price, (string) $order->product_price_snapshot);
        $this->assertEquals((string) $product->duration, (string) $order->product_duration_snapshot);
    }

    public function test_employee_can_logout(): void
    {
        $employee = User::factory()->create([
            'password' => bcrypt('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->post('/employee/login', [
            'login' => $employee->username,
            'password' => 'password',
        ]);

        $this->post('/employee/logout');

        $response = $this->get('/employee/dashboard');
        $response->assertRedirect(route('employee.login'));
    }

    public function test_employee_followups_page_renders(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/follow-ups');

        $response->assertStatus(200);
    }

    public function test_employee_notifications_page_renders(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/notifications');

        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_other_teams_data(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);
        $employeeA = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $teamA->id,
        ]);

        SalesOrder::factory()->create([
            'team_id' => $teamB->id,
            'product_id' => $product->id,
        ]);

        $this->actingAsEmployee($employeeA);

        $response = $this->get('/team/customers');
        $response->assertStatus(302);
    }
}
