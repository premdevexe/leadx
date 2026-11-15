<?php

namespace Tests\Feature\WebPortal;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmCustomerIssuesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsEmployee(User $employee)
    {
        $this->withSession(['employee_id' => $employee->id]);
        $this->app['request']->setUserResolver(fn () => $employee);

        return $this;
    }

    /**
     * Create an employee, team, product, and customers. Returns
     * [customerA, customerB, team, product].
     *
     * Customers' phones are also set on sales orders so getAuthorizedCustomerIds
     * can match them.
     */
    private function seedEmployeeWithCustomers(User $employee): array
    {
        $team = Team::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);
        $employee->update(['team_id' => $team->id]);

        $customerA = Customer::factory()->create(['team_id' => $team->id]);
        $customerB = Customer::factory()->create(['team_id' => $team->id]);

        // Create orders so that each customer matches the employee via phone
        SalesOrder::factory()->create([
            'assigned_employee_id' => $employee->id,
            'team_id' => $team->id,
            'product_id' => $product->id,
            'customer_name' => $customerA->name,
            'customer_phone' => $customerA->phone,
            'customer_email' => $customerA->email,
        ]);
        SalesOrder::factory()->create([
            'assigned_employee_id' => $employee->id,
            'team_id' => $team->id,
            'product_id' => $product->id,
            'customer_name' => $customerB->name,
            'customer_phone' => $customerB->phone,
            'customer_email' => $customerB->email,
        ]);

        return [$customerA, $customerB, $team, $product];
    }

    // 1. Employee can access the customer issues page
    public function test_employee_can_access_customer_issues_page(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/customer-issues');
        $response->assertStatus(200);
        $response->assertSee('CRM Customer Issues');
    }

    // 2. Unauthenticated user is redirected
    public function test_unauthenticated_user_is_redirected_from_customer_issues(): void
    {
        $response = $this->get('/employee/customer-issues');
        $response->assertRedirect(route('employee.login'));
    }

    // 3. New issue defaults to Still Facing
    public function test_new_issue_defaults_to_still_facing(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA] = $this->seedEmployeeWithCustomers($employee);

        $this->actingAsEmployee($employee);

        $response = $this->post('/employee/customer-issues', [
            'customer_id' => $customerA->id,
            'issue_title' => 'API Key not working',
            'issue_description' => 'Customer cannot authenticate via API.',
            'issue_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('employee.customer-issues.index'));
        $this->assertDatabaseHas('crm_customer_issues', [
            'customer_id' => $customerA->id,
            'employee_id' => $employee->id,
            'issue_title' => 'API Key not working',
            'status' => 'still_facing',
        ]);
    }

    // 4. Employee can mark issue Fixed
    public function test_employee_can_mark_issue_fixed(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA] = $this->seedEmployeeWithCustomers($employee);
        $issue = \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Login problem',
            'issue_description' => 'Customer cannot login.',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->put("/employee/customer-issues/{$issue->id}", [
            'customer_id' => $customerA->id,
            'issue_title' => 'Login problem',
            'issue_description' => 'Customer cannot login.',
            'issue_date' => now()->toDateString(),
            'status' => 'fixed',
            'update_note' => 'Resolved by resetting password.',
        ]);

        $response->assertRedirect(route('employee.customer-issues.show', $issue));
        $this->assertDatabaseHas('crm_customer_issues', [
            'id' => $issue->id,
            'status' => 'fixed',
        ]);
    }

    // 5. Employee can reopen a Fixed issue
    public function test_employee_can_reopen_fixed_issue(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA] = $this->seedEmployeeWithCustomers($employee);
        $issue = \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Payment issue',
            'issue_description' => 'Payment verification pending.',
            'status' => 'fixed',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->put("/employee/customer-issues/{$issue->id}", [
            'customer_id' => $customerA->id,
            'issue_title' => 'Payment issue',
            'issue_description' => 'Payment verification pending.',
            'issue_date' => now()->toDateString(),
            'status' => 'still_facing',
            'update_note' => 'Customer reports the problem is back.',
        ]);

        $response->assertRedirect(route('employee.customer-issues.show', $issue));
        $this->assertDatabaseHas('crm_customer_issues', [
            'id' => $issue->id,
            'status' => 'still_facing',
        ]);
    }

    // 6. Issue history is recorded on status change
    public function test_issue_history_is_recorded_on_status_change(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA] = $this->seedEmployeeWithCustomers($employee);
        $issue = \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Technical error',
            'issue_description' => 'System shows error on checkout.',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee($employee);

        $this->put("/employee/customer-issues/{$issue->id}", [
            'customer_id' => $customerA->id,
            'issue_title' => 'Technical error',
            'issue_description' => 'System shows error on checkout.',
            'issue_date' => now()->toDateString(),
            'status' => 'fixed',
            'update_note' => 'Fixed by deploying hotfix.',
        ]);

        $this->assertDatabaseHas('crm_customer_issue_histories', [
            'issue_id' => $issue->id,
            'old_status' => 'still_facing',
            'new_status' => 'fixed',
            'update_note' => 'Fixed by deploying hotfix.',
            'employee_id' => $employee->id,
        ]);
    }

    // 7. Employee A cannot view Employee B's issue
    public function test_employee_a_cannot_view_employee_b_issue(): void
    {
        $employeeA = User::factory()->create(['role' => 'employee', 'status' => 'active']);
        $employeeB = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        [$customerB] = $this->seedEmployeeWithCustomers($employeeB);
        $issueB = \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employeeB->id,
            'customer_id' => $customerB->id,
            'issue_title' => 'B\'s issue',
            'issue_description' => 'Secret B issue.',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee($employeeA);

        $response = $this->get("/employee/customer-issues/{$issueB->id}");
        $response->assertRedirect(route('employee.customer-issues.index'));
        $response->assertSessionHas('error');
    }

    // 8. Employee A cannot edit Employee B's issue
    public function test_employee_a_cannot_edit_employee_b_issue(): void
    {
        $employeeA = User::factory()->create(['role' => 'employee', 'status' => 'active']);
        $employeeB = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        [$customerB] = $this->seedEmployeeWithCustomers($employeeB);
        $issueB = \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employeeB->id,
            'customer_id' => $customerB->id,
            'issue_title' => 'B\'s issue',
            'issue_description' => 'Secret B issue.',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee($employeeA);

        $response = $this->put("/employee/customer-issues/{$issueB->id}", [
            'customer_id' => $customerB->id,
            'issue_title' => 'Hacked title',
            'issue_description' => 'Hacked description.',
            'issue_date' => now()->toDateString(),
            'status' => 'fixed',
        ]);

        $response->assertRedirect(route('employee.customer-issues.index'));
        $this->assertDatabaseHas('crm_customer_issues', [
            'id' => $issueB->id,
            'issue_title' => 'B\'s issue',
        ]);
    }

    // 9. Employee A cannot change Employee B's issue status
    public function test_employee_a_cannot_change_employee_b_status(): void
    {
        $employeeA = User::factory()->create(['role' => 'employee', 'status' => 'active']);
        $employeeB = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        [$customerB] = $this->seedEmployeeWithCustomers($employeeB);
        $issueB = \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employeeB->id,
            'customer_id' => $customerB->id,
            'issue_title' => 'B\'s issue',
            'issue_description' => 'Secret B issue.',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee($employeeA);

        $response = $this->put("/employee/customer-issues/{$issueB->id}", [
            'customer_id' => $customerB->id,
            'issue_title' => 'B\'s issue',
            'issue_description' => 'Secret B issue.',
            'issue_date' => now()->toDateString(),
            'status' => 'fixed',
        ]);

        $response->assertRedirect(route('employee.customer-issues.index'));
        $this->assertDatabaseHas('crm_customer_issues', [
            'id' => $issueB->id,
            'status' => 'still_facing',
        ]);
    }

    // 10. Unauthorized URL access is blocked (non-existent issue)
    public function test_employee_cannot_access_nonexistent_issue(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/customer-issues/999999');
        $response->assertStatus(404);
    }

    // 11. Dashboard counts are scoped to authenticated employee
    public function test_dashboard_counts_are_scoped_to_authenticated_employee(): void
    {
        $employeeA = User::factory()->create(['role' => 'employee', 'status' => 'active']);
        $employeeB = User::factory()->create(['role' => 'employee', 'status' => 'active']);

        [$customerA] = $this->seedEmployeeWithCustomers($employeeA);
        [$customerB] = $this->seedEmployeeWithCustomers($employeeB);

        // Employee A creates 2 issues
        \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employeeA->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Issue A1',
            'issue_description' => 'Desc A1',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);
        \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employeeA->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Issue A2',
            'issue_description' => 'Desc A2',
            'status' => 'fixed',
            'issue_date' => now()->toDateString(),
        ]);

        // Employee B creates 8 issues
        for ($i = 1; $i <= 8; $i++) {
            \App\Models\CrmCustomerIssue::create([
                'employee_id' => $employeeB->id,
                'customer_id' => $customerB->id,
                'issue_title' => "Issue B{$i}",
                'issue_description' => "Desc B{$i}",
                'status' => 'still_facing',
                'issue_date' => now()->toDateString(),
            ]);
        }

        $this->actingAsEmployee($employeeA);

        $response = $this->get('/employee/customer-issues');
        $response->assertStatus(200);
        $response->assertViewHas('totalIssues', 2);
        $response->assertViewHas('fixedIssues', 1);
        $response->assertViewHas('stillFacingIssues', 1);
        $response->assertViewHas('customersAffected', 1);
    }

    // 12. Search filters issues by customer name
    public function test_search_filters_issues_by_customer_name(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA, $customerB] = $this->seedEmployeeWithCustomers($employee);

        \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Alpha issue',
            'issue_description' => 'Alpha desc',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);
        \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerB->id,
            'issue_title' => 'Beta issue',
            'issue_description' => 'Beta desc',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/customer-issues?search=' . urlencode($customerA->name));
        $response->assertStatus(200);
        $response->assertSee('Alpha issue');
        $response->assertDontSee('Beta issue');
    }

    // 13. Status filter works correctly
    public function test_status_filter_works(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA] = $this->seedEmployeeWithCustomers($employee);

        \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Issue fixed',
            'issue_description' => 'Desc',
            'status' => 'fixed',
            'issue_date' => now()->toDateString(),
        ]);
        \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Issue pending',
            'issue_description' => 'Desc',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/customer-issues?status=fixed');
        $response->assertStatus(200);
        $response->assertSee('Issue fixed');
        $response->assertDontSee('Issue pending');
    }

    // 14. Employee can only select authorized CRM customers (unauthorized customer rejected)
    public function test_employee_cannot_create_issue_for_unauthorized_customer(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA] = $this->seedEmployeeWithCustomers($employee);
        $unauthorizedCustomer = Customer::factory()->create(); // no matching order for employee

        $this->actingAsEmployee($employee);

        $response = $this->post('/employee/customer-issues', [
            'customer_id' => $unauthorizedCustomer->id,
            'issue_title' => 'Test issue',
            'issue_description' => 'Test desc',
            'issue_date' => now()->format('Y-m-d'),
            'status' => 'still_facing',
        ]);

        $response->assertSessionHasErrors('customer_id');
        $this->assertDatabaseMissing('crm_customer_issues', [
            'customer_id' => $unauthorizedCustomer->id,
        ]);
    }

    // 15. Create form shows only authorized customers
    public function test_create_form_shows_only_authorized_customers(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA] = $this->seedEmployeeWithCustomers($employee);
        $unauthorizedCustomer = Customer::factory()->create();

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/customer-issues/create');
        $response->assertStatus(200);
        $response->assertSee($customerA->name);
        $response->assertDontSee($unauthorizedCustomer->name);
    }

    // 16. Sidebar menu item is visible
    public function test_sidebar_crm_customer_issues_link_present(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/dashboard');
        $response->assertStatus(200);
        $response->assertSee('CRM Customer Issues');
    }

    // 17. Pagination works
    public function test_pagination_works(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA] = $this->seedEmployeeWithCustomers($employee);

        foreach (range(1, 20) as $i) {
            \App\Models\CrmCustomerIssue::create([
                'employee_id' => $employee->id,
                'customer_id' => $customerA->id,
                'issue_title' => "Issue {$i}",
                'issue_description' => "Desc {$i}",
                'status' => 'still_facing',
                'issue_date' => now()->toDateString(),
            ]);
        }

        $this->actingAsEmployee($employee);

        $response = $this->get('/employee/customer-issues');
        $response->assertStatus(200);
        // 20 issues with 15 per page => page 2 exists
        $response->assertSee('2');
    }

    // 18. Date filter works
    public function test_date_filter_works(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA] = $this->seedEmployeeWithCustomers($employee);

        \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Old issue',
            'issue_description' => 'Desc',
            'status' => 'still_facing',
            'issue_date' => now()->subDays(30)->toDateString(),
        ]);
        \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Recent issue',
            'issue_description' => 'Desc',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee($employee);

        $today = now()->toDateString();
        $response = $this->get("/employee/customer-issues?date_from={$today}&date_to={$today}");
        $response->assertStatus(200);
        $response->assertSee('Recent issue');
        $response->assertDontSee('Old issue');
    }

    // 19. Form validates required fields
    public function test_create_issue_validates_required_fields(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->seedEmployeeWithCustomers($employee);
        $this->actingAsEmployee($employee);

        $response = $this->post('/employee/customer-issues', [
            'issue_title' => '',
            'issue_description' => '',
        ]);

        $response->assertSessionHasErrors(['customer_id', 'issue_title', 'issue_description']);
    }

    // 20. Employee can view own issue detail
    public function test_employee_can_view_own_issue_detail(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
        ]);

        [$customerA] = $this->seedEmployeeWithCustomers($employee);
        $issue = \App\Models\CrmCustomerIssue::create([
            'employee_id' => $employee->id,
            'customer_id' => $customerA->id,
            'issue_title' => 'Technical error',
            'issue_description' => 'System shows error.',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);

        $this->actingAsEmployee($employee);

        $response = $this->get("/employee/customer-issues/{$issue->id}");
        $response->assertStatus(200);
        $response->assertSee('Technical error');
        $response->assertSee('System shows error.');
    }
}
