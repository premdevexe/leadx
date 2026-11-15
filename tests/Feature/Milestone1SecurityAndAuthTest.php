<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\OrderApiCredential;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderCredentialService;
use App\Services\OrderNumberService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class Milestone1SecurityAndAuthTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Super Admin Authentication & Protection
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_can_login_with_username_or_email_and_regenerates_session(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'admin_tester',
            'email' => 'admin_tester@leadx.test',
            'password' => Hash::make('Secret123!'),
        ]);

        // Login with username
        $response = $this->post('/admin/login', [
            'login' => 'admin_tester',
            'password' => 'Secret123!',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');

        // Logout
        $this->post('/admin/logout');
        $this->assertGuest('admin');

        // Login with email
        $responseEmail = $this->post('/admin/login', [
            'login' => 'admin_tester@leadx.test',
            'password' => 'Secret123!',
        ]);

        $responseEmail->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_super_admin_login_rate_limiting_protects_against_brute_force(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'admin_brute',
            'password' => Hash::make('CorrectPassword!'),
        ]);

        // Attempt 5 incorrect logins
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/admin/login', [
                'login' => 'admin_brute',
                'password' => 'WrongPassword',
            ]);
            $response->assertSessionHasErrors('login');
        }

        // 6th attempt should be blocked by rate limiter
        $responseBlocked = $this->post('/admin/login', [
            'login' => 'admin_brute',
            'password' => 'WrongPassword',
        ]);

        $responseBlocked->assertSessionHasErrors('login');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('login'));
    }

    public function test_admin_routes_are_protected_by_authenticate_admin_middleware(): void
    {
        // Unauthenticated access to admin dashboard should redirect to admin login
        $response = $this->get('/admin');
        $response->assertRedirect(route('admin.login'));

        $responseEmployees = $this->get('/admin/employees');
        $responseEmployees->assertRedirect(route('admin.login'));
    }

    /*
    |--------------------------------------------------------------------------
    | Employee Web Authentication Standard
    |--------------------------------------------------------------------------
    */

    public function test_active_employee_can_login_via_employee_portal(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create([
            'username' => 'active_emp',
            'email' => 'active_emp@leadx.test',
            'password' => Hash::make('EmployeePass123!'),
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $team->id,
        ]);

        $response = $this->post('/employee/login', [
            'login' => 'active_emp',
            'password' => 'EmployeePass123!',
        ]);

        $response->assertRedirect(route('employee.dashboard'));
        $this->assertAuthenticatedAs($employee, 'employee');
        $this->assertEquals($employee->id, session('employee_id'));

        // Can access dashboard
        $dashResponse = $this->get('/employee/dashboard');
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee($employee->name);
    }

    public function test_inactive_employee_cannot_login(): void
    {
        $employee = User::factory()->create([
            'username' => 'inactive_emp',
            'password' => Hash::make('EmployeePass123!'),
            'role' => 'employee',
            'status' => 'inactive',
        ]);

        $response = $this->post('/employee/login', [
            'login' => 'inactive_emp',
            'password' => 'EmployeePass123!',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertStringContainsString('inactive', session('errors')->first('login'));
        $this->assertGuest('employee');
    }

    public function test_non_employee_role_cannot_login_via_employee_portal(): void
    {
        $adminUser = User::factory()->create([
            'username' => 'fake_emp_admin',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->post('/employee/login', [
            'login' => 'fake_emp_admin',
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertStringContainsString('not an employee', session('errors')->first('login'));
        $this->assertGuest('employee');
    }

    public function test_employee_logout_clears_guard_and_session(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAs($employee, 'employee');
        session(['employee_id' => $employee->id]);

        $response = $this->post('/employee/logout');

        $response->assertRedirect(route('employee.login'));
        $this->assertGuest('employee');
        $this->assertNull(session('employee_id'));
    }

    /*
    |--------------------------------------------------------------------------
    | Team Login Deprecation
    |--------------------------------------------------------------------------
    */

    public function test_web_team_login_get_redirects_to_employee_login_with_info_message(): void
    {
        $response = $this->get('/team/login');

        $response->assertRedirect(route('employee.login'));
        $response->assertSessionHas('info', 'Team login has been deprecated. Please log in with your individual employee account.');
    }

    public function test_web_team_login_post_redirects_to_employee_login(): void
    {
        $team = Team::factory()->create(['password' => Hash::make('TeamSecret123!')]);

        $response = $this->post('/team/login', [
            'login' => $team->username,
            'password' => 'TeamSecret123!',
        ]);

        $response->assertRedirect(route('employee.login'));
        $response->assertSessionHas('info');
    }

    public function test_web_team_logout_clears_session_and_redirects_to_employee_login(): void
    {
        $team = Team::factory()->create();

        $response = $this->withSession(['team_id' => $team->id])
            ->post('/team/logout');

        $response->assertRedirect(route('employee.login'));
        $this->assertNull(session('team_id'));
    }

    public function test_api_team_login_returns_410_gone(): void
    {
        $team = Team::factory()->create(['password' => Hash::make('TeamSecret123!')]);

        $response = $this->postJson('/api/v1/team/login', [
            'login' => $team->username,
            'password' => 'TeamSecret123!',
        ]);

        $response->assertStatus(410);
        $response->assertJson([
            'message' => 'Team login has been deprecated. Please authenticate using employee credentials at /api/v1/employee/login.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Sequence Non-Burning on GET Requests (peekNext)
    |--------------------------------------------------------------------------
    */

    public function test_visiting_employee_create_order_page_does_not_burn_sequence_number(): void
    {
        $team = Team::factory()->create(['team_code' => 'ALPHA']);
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $team->id,
        ]);
        Product::factory()->create(['status' => 'active']);

        $service = app(OrderNumberService::class);
        $year = (int) now()->format('Y');

        // Initial preview call
        $preview1 = $service->peekNext($team);
        $this->assertStringStartsWith('ALPHA-'.$year.'-', $preview1);

        // Verify sequence table was NOT incremented
        $seqRowBefore = DB::table('order_number_sequences')
            ->where('team_id', $team->id)
            ->where('year', $year)
            ->first();
        $this->assertNull($seqRowBefore);

        // Simulate visiting employee order create page 3 times via GET
        $this->actingAs($employee, 'employee');
        session(['employee_id' => $employee->id]);

        $this->get(route('employee.orders.create'))->assertStatus(200);
        $this->get(route('employee.orders.create'))->assertStatus(200);
        $this->get(route('employee.orders.create'))->assertStatus(200);

        // Sequence row must STILL not exist or remain at 0
        $seqRowAfter = DB::table('order_number_sequences')
            ->where('team_id', $team->id)
            ->where('year', $year)
            ->first();
        $this->assertNull($seqRowAfter);

        // The preview remains identical
        $preview2 = $service->peekNext($team);
        $this->assertEquals($preview1, $preview2);
    }

    /*
    |--------------------------------------------------------------------------
    | Critical Cross-Employee IDOR Protection
    |--------------------------------------------------------------------------
    */

    public function test_employee_cannot_view_or_access_another_employees_order_in_same_team_via_api(): void
    {
        $team = Team::factory()->create();
        $empA = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $empB = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $product = Product::factory()->create(['status' => 'active']);

        $orderB = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $empB->id,
            'product_id' => $product->id,
            'order_number' => 'TEST-2026-9999',
        ]);

        $tokenA = $empA->createToken('empA-token')->plainTextToken;

        // Emp A attempts to show Emp B's order via GET /api/v1/orders/{id}
        $responseShow = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/orders/'.$orderB->id);

        $responseShow->assertStatus(422); // assertCanAccess throws ValidationException
        $responseShow->assertJsonValidationErrors('order');

        // Emp A attempts to access Emp B's order by order_number
        $responseByNum = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/orders/by-number/'.$orderB->order_number);

        $responseByNum->assertStatus(422);
        $responseByNum->assertJsonValidationErrors('order');

        // Emp A attempts to activate Emp B's order
        $responseActivate = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->putJson('/api/v1/orders/'.$orderB->id.'/activate');

        $responseActivate->assertStatus(422);
        $responseActivate->assertJsonValidationErrors('order');
    }

    public function test_employee_only_receives_own_assigned_orders_in_api_index(): void
    {
        $team = Team::factory()->create();
        $empA = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $empB = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $product = Product::factory()->create(['status' => 'active']);

        $orderA = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $empA->id,
            'product_id' => $product->id,
            'order_number' => 'ORD-EMPA-001',
        ]);

        $orderB = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $empB->id,
            'product_id' => $product->id,
            'order_number' => 'ORD-EMPB-002',
        ]);

        $tokenA = $empA->createToken('empA-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $orderNumbers = collect($response->json('data'))->pluck('order_number')->all();

        $this->assertContains('ORD-EMPA-001', $orderNumbers);
        $this->assertNotContains('ORD-EMPB-002', $orderNumbers);
    }

    public function test_employee_cannot_view_another_employees_order_via_web_portal(): void
    {
        $team = Team::factory()->create();
        $empA = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $empB = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $product = Product::factory()->create(['status' => 'active']);

        $orderB = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $empB->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($empA, 'employee');
        session(['employee_id' => $empA->id]);

        $response = $this->get('/employee/orders/'.$orderB->id);
        $response->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | API Key Exposure Protection
    |--------------------------------------------------------------------------
    */

    public function test_employee_cannot_reveal_api_key_of_another_employee_in_same_team(): void
    {
        $team = Team::factory()->create();
        $empA = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $empB = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $product = Product::factory()->create(['status' => 'active']);

        $orderB = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $empB->id,
            'product_id' => $product->id,
        ]);

        $credentialService = app(OrderCredentialService::class);
        $credentialService->store($orderB, 'sk-leadx-super-secret-key-12345', $empB);

        // Employee A cannot manage or reveal Emp B's key
        $this->assertFalse($credentialService->canManage($orderB, $empA));
        $this->assertNull($credentialService->reveal($orderB, $empA));

        // Team model cannot manage or reveal
        $this->assertFalse($credentialService->canManage($orderB, $team));
        $this->assertNull($credentialService->reveal($orderB, $team));

        // Employee B (the owner) CAN manage and reveal
        $this->assertTrue($credentialService->canManage($orderB, $empB));
        $this->assertEquals('sk-leadx-super-secret-key-12345', $credentialService->reveal($orderB, $empB));

        // Super Admin CAN manage and reveal any order's key
        $admin = Admin::factory()->create();
        $this->assertTrue($credentialService->canManage($orderB, $admin));
        $this->assertEquals('sk-leadx-super-secret-key-12345', $credentialService->reveal($orderB, $admin));
    }

    /*
    |--------------------------------------------------------------------------
    | Additional Authentication, Sequence & IDOR Hardening Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_users_cannot_access_employee_web_or_api_routes(): void
    {
        $this->get('/employee/dashboard')->assertRedirect(route('employee.login'));
        $this->get('/employee/orders')->assertRedirect(route('employee.login'));
        $this->get('/employee/payments')->assertRedirect(route('employee.login'));
        $this->get('/employee/customers')->assertRedirect(route('employee.login'));
        $this->get('/employee/subscriptions')->assertRedirect(route('employee.login'));

        $this->getJson('/api/v1/employee/profile')->assertStatus(401);
        $this->getJson('/api/v1/orders')->assertStatus(401);
        $this->getJson('/api/v1/subscriptions')->assertStatus(401);
    }

    public function test_non_employee_role_rejected_from_api_employee_login(): void
    {
        User::factory()->create([
            'username' => 'api_admin_user',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/employee/login', [
            'login' => 'api_admin_user',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('login');
        $this->assertStringContainsString('not an employee', $response->json('errors.login.0'));
    }

    public function test_inactive_employee_token_rejected_by_ensure_role_active(): void
    {
        $employee = User::factory()->create([
            'username' => 'active_then_disabled',
            'password' => Hash::make('Password123!'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $employee->createToken('test-token')->plainTextToken;

        // Access while active
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/employee/profile')
            ->assertOk();

        // Deactivate employee account
        $employee->update(['status' => 'inactive']);

        // Next request must be rejected with 403
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/employee/profile')
            ->assertStatus(403);
    }

    public function test_successful_order_creation_consumes_sequence_once_and_preserves_format(): void
    {
        $team = Team::factory()->create(['team_code' => 'XYZ']);
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $product = Product::factory()->create(['status' => 'active', 'price' => 100.00]);
        $year = (int) now()->format('Y');

        $this->actingAs($employee, 'employee');
        session(['employee_id' => $employee->id]);

        $response = $this->post('/employee/orders', [
            'customer_name' => 'Alice Customer',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => '100.00',
        ]);

        $response->assertRedirect();

        $order = SalesOrder::where('assigned_employee_id', $employee->id)->first();
        $this->assertNotNull($order);
        $expectedOrderNumber = sprintf('XYZ-%d-000001', $year);
        $this->assertEquals($expectedOrderNumber, $order->order_number);

        // Sequence row should be at 1
        $seq = DB::table('order_number_sequences')->where('team_id', $team->id)->where('year', $year)->first();
        $this->assertEquals(1, $seq->last_sequence);

        // Create second order
        $this->post('/employee/orders', [
            'customer_name' => 'Bob Customer',
            'product_id' => $product->id,
            'duration' => '1y',
            'closing_value' => '100.00',
        ]);

        $order2 = SalesOrder::where('assigned_employee_id', $employee->id)->orderByDesc('id')->first();
        $this->assertEquals(sprintf('XYZ-%d-000002', $year), $order2->order_number);

        $seq2 = DB::table('order_number_sequences')->where('team_id', $team->id)->where('year', $year)->first();
        $this->assertEquals(2, $seq2->last_sequence);
    }

    public function test_failed_order_creation_inside_transaction_rolls_back_sequence(): void
    {
        $team = Team::factory()->create(['team_code' => 'ROLL']);
        $employee = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $year = (int) now()->format('Y');

        $orderService = app(OrderService::class);

        // First verify sequence starts at null
        $seq = DB::table('order_number_sequences')->where('team_id', $team->id)->where('year', $year)->first();
        $this->assertNull($seq);

        try {
            DB::transaction(function () use ($orderService, $team, $employee) {
                $orderService->createForTeam($team, $employee, [
                    'customer_name' => 'Will Fail',
                    'closing_value' => 50,
                ]);
                throw new \RuntimeException('Simulated order processing failure');
            });
        } catch (\RuntimeException $e) {
            // Expected exception
        }

        // Sequence must NOT have been committed
        $seqAfter = DB::table('order_number_sequences')->where('team_id', $team->id)->where('year', $year)->first();
        $this->assertNull($seqAfter);

        // Next valid creation gets 000001
        $order = $orderService->createForTeam($team, $employee, [
            'customer_name' => 'Valid Customer',
            'closing_value' => 50,
        ]);

        $this->assertEquals(sprintf('ROLL-%d-000001', $year), $order->order_number);
    }

    public function test_employee_cannot_view_another_employees_payments_via_web(): void
    {
        $team = Team::factory()->create();
        $empA = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $empB = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);

        $orderA = SalesOrder::factory()->create(['team_id' => $team->id, 'assigned_employee_id' => $empA->id]);
        $orderB = SalesOrder::factory()->create(['team_id' => $team->id, 'assigned_employee_id' => $empB->id]);

        Payment::factory()->create(['order_id' => $orderA->id, 'amount' => 500.00]);
        Payment::factory()->create(['order_id' => $orderB->id, 'amount' => 999.00]);

        $this->actingAs($empA, 'employee');
        session(['employee_id' => $empA->id]);

        $response = $this->get('/employee/payments');
        $response->assertStatus(200);
        $response->assertSee('500.00');
        $response->assertDontSee('999.00');
    }

    public function test_employee_cannot_view_another_employees_subscriptions_via_web_or_api(): void
    {
        $team = Team::factory()->create();
        $empA = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $empB = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);

        $orderA = SalesOrder::factory()->create(['team_id' => $team->id, 'assigned_employee_id' => $empA->id]);
        $orderB = SalesOrder::factory()->create(['team_id' => $team->id, 'assigned_employee_id' => $empB->id]);

        $subA = Subscription::factory()->create(['order_id' => $orderA->id, 'team_id' => $team->id, 'status' => 'active']);
        $subB = Subscription::factory()->create(['order_id' => $orderB->id, 'team_id' => $team->id, 'status' => 'active']);

        // Web check
        $this->actingAs($empA, 'employee');
        session(['employee_id' => $empA->id]);

        $responseWeb = $this->get('/employee/subscriptions');
        $responseWeb->assertStatus(200);
        $responseWeb->assertSee($orderA->order_number);
        $responseWeb->assertDontSee($orderB->order_number);

        // API index check
        $tokenA = $empA->createToken('tokenA')->plainTextToken;
        $responseApi = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/subscriptions');

        $responseApi->assertOk();
        $subIds = collect($responseApi->json('data'))->pluck('id')->all();
        $this->assertContains($subA->id, $subIds);
        $this->assertNotContains($subB->id, $subIds);

        // API show check: Emp A tries to access Emp B's subscription
        $responseShow = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/subscriptions/'.$subB->id);

        $responseShow->assertStatus(422);
    }

    public function test_employee_cannot_view_another_employees_customers_via_web_or_api(): void
    {
        $team = Team::factory()->create();
        $empA = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $empB = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);

        SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $empA->id,
            'customer_phone' => '+1111111111',
            'customer_name' => 'Alice Customer',
        ]);
        SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $empB->id,
            'customer_phone' => '+2222222222',
            'customer_name' => 'Bob Private Customer',
        ]);

        Customer::factory()->paying()->create([
            'team_id' => $team->id,
            'name' => 'Alice Customer',
            'phone' => '+1111111111',
        ]);
        Customer::factory()->paying()->create([
            'team_id' => $team->id,
            'name' => 'Bob Private Customer',
            'phone' => '+2222222222',
        ]);

        // Web check
        $this->actingAs($empA, 'employee');
        session(['employee_id' => $empA->id]);

        $responseWeb = $this->get('/employee/customers');
        $responseWeb->assertStatus(200);
        $responseWeb->assertSee('Alice Customer');
        $responseWeb->assertDontSee('Bob Private Customer');

        // API verified customers check
        $tokenA = $empA->createToken('tokenA')->plainTextToken;
        $responseApi = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/customers/verified');

        $responseApi->assertOk();
        $names = collect($responseApi->json('data'))->pluck('name')->all();
        $this->assertContains('Alice Customer', $names);
        $this->assertNotContains('Bob Private Customer', $names);
    }

    public function test_employee_cannot_view_or_manage_another_employees_follow_up_in_same_team(): void
    {
        $team = Team::factory()->create();
        $empA = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $empB = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $customer = Customer::factory()->paying()->create(['team_id' => $team->id]);

        $followUpB = FollowUp::factory()->create([
            'customer_id' => $customer->id,
            'team_id' => $team->id,
            'assigned_employee_id' => $empB->id,
            'title' => 'Emp B Private Follow-up',
        ]);

        $tokenA = $empA->createToken('tokenA')->plainTextToken;

        // Emp A tries to show Emp B's follow-up
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/follow-ups/'.$followUpB->id)
            ->assertStatus(422);

        // Emp A tries to get Emp B's follow-up history
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/follow-ups/'.$followUpB->id.'/history')
            ->assertStatus(422);

        // Emp A lists follow-ups - must not see Emp B's follow-up
        $responseList = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/follow-ups');

        $responseList->assertOk();
        $titles = collect($responseList->json('data'))->pluck('title')->all();
        $this->assertNotContains('Emp B Private Follow-up', $titles);
    }

    public function test_employee_cannot_attach_another_employees_order_when_creating_follow_up(): void
    {
        $team = Team::factory()->create();
        $empA = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $empB = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $customer = Customer::factory()->paying()->create(['team_id' => $team->id]);

        $orderB = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $empB->id,
        ]);

        $tokenA = $empA->createToken('tokenA')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/follow-ups', [
                'customer_id' => $customer->id,
                'order_id' => $orderB->id,
                'title' => 'Invalid attachment',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('order_id');
    }

    public function test_employee_dashboard_metrics_are_scoped_strictly_to_employee(): void
    {
        $team = Team::factory()->create();
        $empA = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);
        $empB = User::factory()->create(['role' => 'employee', 'status' => 'active', 'team_id' => $team->id]);

        $orderA = SalesOrder::factory()->create(['team_id' => $team->id, 'assigned_employee_id' => $empA->id]);
        $ordersB = SalesOrder::factory()->count(3)->create(['team_id' => $team->id, 'assigned_employee_id' => $empB->id]);

        // 1 subscription for Emp A, 3 subscriptions for Emp B in same team
        Subscription::factory()->create(['order_id' => $orderA->id, 'team_id' => $team->id, 'status' => 'active']);
        foreach ($ordersB as $ob) {
            Subscription::factory()->create(['order_id' => $ob->id, 'team_id' => $team->id, 'status' => 'active']);
        }

        $this->actingAs($empA, 'employee');
        session(['employee_id' => $empA->id]);

        $response = $this->get('/employee/dashboard');
        $response->assertStatus(200);

        // In Emp A's view data, activeSubscriptions must be 1, NOT 4
        $this->assertEquals(1, $response->viewData('activeSubscriptions'));
    }
}
