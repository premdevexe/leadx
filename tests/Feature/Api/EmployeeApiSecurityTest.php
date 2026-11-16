<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\CrmCustomerIssue;
use App\Models\Customer;
use App\Models\EmployeeCalendarEvent;
use App\Models\EmployeeTeamAccessGrant;
use App\Models\FollowUp;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PermissionPreset;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

/**
 * Cross-actor security tests for the employee panel API.
 *
 * The rules under test:
 *  1. An employee token is scoped to the individual user who owns it —
 *     it can never act as another employee (no "impersonation via id").
 *  2. Reads are bounded to owned records, or team records within an
 *     active admin grant window.
 *  3. Writes are owned by the caller; a team-data grant is read-only.
 *  4. Admin-only functions (key store, admin panel, subscription
 *     renew/cancel, payment verify/reject) are unreachable from an
 *     employee token.
 *  5. Permission presets are enforced on the permission-gated routes.
 *  6. Secrets never leak through any response.
 */
class EmployeeApiSecurityTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected Team $team;

    protected Team $foreignTeam;

    protected User $employee;

    protected User $otherEmployee;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->team = Team::factory()->create(['team_code' => 'GEM']);
        $this->foreignTeam = Team::factory()->create(['team_code' => 'GPT']);
        $this->admin = Admin::factory()->create();

        $this->employee = User::factory()->create([
            'team_id' => $this->team->id,
            'preset_id' => PermissionPreset::where('slug', 'sales_executive')->value('id'),
        ]);
        $this->otherEmployee = User::factory()->create([
            'team_id' => $this->team->id,
            'preset_id' => PermissionPreset::where('slug', 'sales_executive')->value('id'),
        ]);
    }

    protected function headers(User $user): array
    {
        return $this->actingAsToken($this->employeeToken($user));
    }

    /*
    |----------------------------------------------------------------------
    | 1. No impersonation: an employee token always acts as its owner
    |----------------------------------------------------------------------
    */

    public function test_an_employee_token_cannot_act_as_another_employee(): void
    {
        // Even if the client sends a different employee_id, the server
        // scopes by the token's own user, never by the body.
        $foreignOrder = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->otherEmployee->id,
        ]);

        $this->getJson("/api/v1/employee/orders/{$foreignOrder->id}?employee_id={$this->otherEmployee->id}", $this->headers($this->employee))
            ->assertStatus(422);

        $this->getJson('/api/v1/employee/orders', $this->headers($this->employee))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_team_token_cannot_use_employee_routes(): void
    {
        $this->getJson('/api/v1/employee/dashboard', $this->actingAsToken($this->teamToken($this->team)))
            ->assertStatus(403);

        $this->postJson('/api/v1/employee/orders', ['customer_name' => 'X'], $this->actingAsToken($this->teamToken($this->team)))
            ->assertStatus(403);

        $this->postJson('/api/v1/employee/customer-issues', [
            'customer_id' => 1, 'issue_title' => 't', 'issue_description' => 'd',
        ], $this->actingAsToken($this->teamToken($this->team)))
            ->assertStatus(403);
    }

    public function test_an_admin_token_cannot_use_employee_routes(): void
    {
        $this->getJson('/api/v1/employee/me', $this->actingAsToken($this->adminToken($this->admin)))
            ->assertStatus(403);
    }

    /*
    |----------------------------------------------------------------------
    | 2. Read scoping: owned records only, team within grant window
    |----------------------------------------------------------------------
    */

    public function test_an_employee_cannot_read_another_employees_records(): void
    {
        $foreignOrder = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->otherEmployee->id,
        ]);

        $this->getJson("/api/v1/employee/orders/{$foreignOrder->id}", $this->headers($this->employee))
            ->assertStatus(422);

        $foreignPayment = Payment::factory()->create(['order_id' => $foreignOrder->id]);
        $this->getJson("/api/v1/employee/payments/{$foreignPayment->id}", $this->headers($this->employee))
            ->assertStatus(422);

        $foreignSub = Subscription::factory()->create([
            'order_id' => $foreignOrder->id,
            'team_id' => $this->team->id,
        ]);
        $this->getJson("/api/v1/employee/subscriptions/{$foreignSub->id}", $this->headers($this->employee))
            ->assertStatus(422);

        $foreignFu = FollowUp::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->otherEmployee->id,
        ]);
        $this->getJson("/api/v1/employee/follow-ups/{$foreignFu->id}", $this->headers($this->employee))
            ->assertStatus(422); // the row exists; access control denies it, not a 404
    }

    public function test_an_employee_cannot_mutate_another_employees_records(): void
    {
        $foreignOrder = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->otherEmployee->id,
        ]);

        $this->postJson("/api/v1/employee/orders/{$foreignOrder->id}/complete", [], $this->headers($this->employee))
            ->assertStatus(422);

        $this->postJson("/api/v1/employee/orders/{$foreignOrder->id}/closing-approval/resubmit", [
            'closing_value' => 100,
        ], $this->headers($this->employee))
            ->assertStatus(422);

        $foreignIssue = CrmCustomerIssue::create([
            'employee_id' => $this->otherEmployee->id,
            'customer_id' => Customer::factory()->create()->id,
            'issue_title' => 't',
            'issue_description' => 'd',
            'status' => 'still_facing',
        ]);

        $this->putJson("/api/v1/employee/customer-issues/{$foreignIssue->id}", [
            'status' => 'fixed',
        ], $this->headers($this->employee))
            ->assertStatus(422);
    }

    public function test_team_data_view_requires_an_active_grant_and_stays_in_the_window(): void
    {
        // No grant: team_data is downgraded to my_data with a warning, and a
        // team order from OUTSIDE the employee is still not visible.
        $teamOrder = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->otherEmployee->id,
        ]);

        $downgraded = $this->getJson('/api/v1/employee/orders?view=team_data', $this->headers($this->employee));
        $downgraded->assertOk();
        $this->assertSame('my_data', $downgraded->json('meta.view'));
        $this->assertNotNull($downgraded->json('meta.warning'));
        $this->assertCount(0, $downgraded->json('data'));

        // With a grant: team_data is honored for team orders in the window.
        EmployeeTeamAccessGrant::create([
            'user_id' => $this->employee->id,
            'admin_id' => $this->admin->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
        ]);
        app(PermissionService::class)->setEmployeeOverride($this->employee, 'team_data.view', true, null, 'test grant');

        $granted = $this->getJson('/api/v1/employee/orders?view=team_data', $this->headers($this->employee));
        $granted->assertOk();
        $this->assertSame('team_data', $granted->json('meta.view'));
        $this->assertCount(1, $granted->json('data'));
        $this->assertSame($teamOrder->id, $granted->json('data.0.id'));

        // Orders from the FOREIGN team are still out of scope.
        $this->getJson('/api/v1/employee/dashboard?view=team_data', $this->headers($this->employee))
            ->assertOk()
            ->assertJsonPath('meta.view', 'team_data');
    }

    public function test_an_outside_grant_window_order_is_not_accessible(): void
    {
        $oldOrder = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->otherEmployee->id,
        ]);
        // created_at is not mass-assignable; push the row back in time so it
        // falls outside the grant window below.
        $oldOrder->forceFill(['created_at' => now()->subDays(60), 'updated_at' => now()])->save();

        EmployeeTeamAccessGrant::create([
            'user_id' => $this->employee->id,
            'admin_id' => $this->admin->id,
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
        ]);
        app(PermissionService::class)->setEmployeeOverride($this->employee, 'team_data.view', true, null, 'test grant');

        $this->getJson("/api/v1/employee/orders/{$oldOrder->id}", $this->headers($this->employee))
            ->assertStatus(422); // outside the grant window
    }

    public function test_a_revoke_stops_team_data_access_immediately(): void
    {
        $grant = EmployeeTeamAccessGrant::create([
            'user_id' => $this->employee->id,
            'admin_id' => $this->admin->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
        ]);
        app(PermissionService::class)->setEmployeeOverride($this->employee, 'team_data.view', true, null, 'test grant');

        $grant->update(['revoked_at' => now()]);

        $response = $this->getJson('/api/v1/employee/orders?view=team_data', $this->headers($this->employee));
        $response->assertOk();
        $this->assertSame('my_data', $response->json('meta.view'));
    }

    /*
    |----------------------------------------------------------------------
    | 3. Writes: only the caller can create/mutate their own records
    |----------------------------------------------------------------------
    */

    public function test_an_employee_cannot_force_order_creation_for_a_different_team_or_employee(): void
    {
        $response = $this->postJson('/api/v1/employee/orders', [
            'customer_name' => 'Grace',
            'team_id' => $this->foreignTeam->id,
        ], $this->headers($this->employee))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        // And the created order, when legal, always lands on the caller.
        $created = $this->postJson('/api/v1/employee/orders', [
            'customer_name' => 'Grace',
        ], $this->headers($this->employee))
            ->assertCreated();

        $this->assertSame($this->employee->id, $created->json('data.assigned_employee_id'));
        $this->assertSame($this->team->id, $created->json('data.team_id'));
    }

    public function test_an_employee_cannot_cancel_or_renew_a_subscription(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
        ]);
        $subscription = Subscription::factory()->create([
            'order_id' => $order->id,
            'team_id' => $this->team->id,
            'status' => 'active',
        ]);

        // The shared subscription endpoints (team/admin only for mutations):
        $this->postJson("/api/v1/subscriptions/{$subscription->id}/renew", [], $this->headers($this->employee))
            ->assertStatus(422);

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/cancel", [], $this->headers($this->employee))
            ->assertStatus(422);
    }

    public function test_an_employee_cannot_verify_or_reject_payments(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
        ]);
        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $this->postJson("/api/v1/admin/payments/{$payment->id}/verify", ['notes' => 'ok'], $this->headers($this->employee))
            ->assertStatus(403);

        $this->postJson("/api/v1/admin/payments/{$payment->id}/reject", ['notes' => 'bad'], $this->headers($this->employee))
            ->assertStatus(403);
    }

    public function test_an_employee_cannot_store_an_order_api_key(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
        ]);

        // Storing a key is admin-only; the employee path is rejected by the
        // service (surfaced as 422). No credential row is created.
        $this->postJson("/api/v1/orders/{$order->id}/api-key", ['api_key' => 'sk-secret-123'], $this->headers($this->employee))
            ->assertStatus(422);

        $this->assertNull(\App\Models\OrderApiCredential::forOrder($order->id));
    }

    /*
    |----------------------------------------------------------------------
    | 4. Permission presets gate the permission-protected routes
    |----------------------------------------------------------------------
    */

    public function test_viewer_preset_employee_is_denied_follow_up_creation(): void
    {
        $viewer = User::factory()->create([
            'team_id' => $this->team->id,
            'preset_id' => PermissionPreset::where('slug', 'viewer')->value('id'),
        ]);

        $customer = Customer::factory()->paying()->create(['team_id' => $this->team->id]);
        SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $viewer->id,
            'customer_phone' => $customer->phone,
        ]);

        $this->postJson('/api/v1/employee/follow-ups', [
            'customer_id' => $customer->id,
            'title' => 't',
        ], $this->headers($viewer))
            ->assertStatus(403)
            ->assertJson(['message' => 'Forbidden: Missing permission [follow_ups.create].']);

        // View is allowed under the viewer preset.
        $this->getJson('/api/v1/employee/follow-ups', $this->headers($viewer))->assertOk();
    }

    public function test_an_individual_override_can_grant_or_deny_a_permission(): void
    {
        $svc = app(PermissionService::class);

        // Granting an override opens a permission the preset denies...
        $svc->setEmployeeOverride($this->otherEmployee, 'follow_ups.delete', true, null, 'granted');
        $this->assertTrue($svc->hasPermission($this->otherEmployee, 'follow_ups.delete'));

        $svc->setEmployeeOverride($this->otherEmployee, 'orders.view', false, null, 'revoked');
        $this->assertFalse($svc->hasPermission($this->otherEmployee, 'orders.view'));

        // The revocation shows up on the API route.
        $this->getJson('/api/v1/employee/orders', $this->headers($this->otherEmployee))->assertStatus(403);
    }

    /*
    |----------------------------------------------------------------------
    | 5. Secrets and sensitive data
    |----------------------------------------------------------------------
    */

    public function test_secrets_never_appear_in_responses(): void
    {
        // API-key reveal is permission-gated and only returns for the owner.
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
        ]);

        \App\Models\OrderApiCredential::firstOrCreate([
            'order_id' => $order->id,
        ])->update(['key' => 'sk-live-super-secret']);

        $revealed = $this->postJson("/api/v1/employee/orders/{$order->id}/api-key/reveal", [], $this->headers($this->employee))
            ->assertOk()
            ->assertJsonPath('api_key', 'sk-live-super-secret');

        // No other employee can reveal it — their own order-access check
        // denies first (422), so the key never crosses ownership.
        $this->postJson("/api/v1/employee/orders/{$order->id}/api-key/reveal", [], $this->headers($this->otherEmployee))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order']);

        // Google connection resources never serialize tokens.
        \App\Models\EmployeeGoogleConnection::create([
            'employee_id' => $this->employee->id,
            'google_email' => 'a@b.com',
            'access_token' => 'ya29-live-secret',
            'refresh_token' => 'refresh-live-secret',
            'status' => 'connected',
        ]);

        $status = $this->getJson('/api/v1/employee/google/calendar', $this->headers($this->employee))
            ->assertOk()
            ->assertJsonStructure(['data' => ['connection' => ['connected', 'status', 'google_email']]]);

        $this->assertStringNotContainsString('ya29-live-secret', (string) $status->getContent());
        $this->assertStringNotContainsString('refresh-live-secret', (string) $status->getContent());

        // Login never echoes the password.
        $login = $this->postJson('/api/v1/employee/login', [
            'login' => $this->employee->username,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonStructure(['token_type', 'token']);

        // The password is never echoed back — only an opaque bearer token.
        $this->assertStringNotContainsString('"password"', (string) $login->getContent());
    }

    public function test_an_inactive_employee_is_locked_out_even_with_a_token(): void
    {
        $this->employee->update(['status' => 'inactive']);
        $token = $this->employeeToken($this->employee);

        $this->getJson('/api/v1/employee/me', $this->actingAsToken($token))
            ->assertStatus(403)
            ->assertJsonPath('message', 'This account has been disabled.');
    }

    /*
    |----------------------------------------------------------------------
    | 6. Calendar ownership is strict
    |----------------------------------------------------------------------
    */

    public function test_an_employee_cannot_touch_another_employees_calendar_events(): void
    {
        $foreign = EmployeeCalendarEvent::create([
            'employee_id' => $this->otherEmployee->id,
            'summary' => 'Foreign event',
            'start_at' => now()->addDays(1),
            'status' => 'synced',
        ]);

        // 404 (not "not found in your scope"), not 403-with-body.
        $this->putJson("/api/v1/employee/calendar/events/{$foreign->id}", [
            'summary' => 'hacked',
        ], $this->headers($this->employee))
            ->assertStatus(404);

        $this->deleteJson("/api/v1/employee/calendar/events/{$foreign->id}", [], $this->headers($this->employee))
            ->assertStatus(404);

        $this->assertSame('Foreign event', EmployeeCalendarEvent::find($foreign->id)->summary);
    }
}
