<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\CrmCustomerIssue;
use App\Models\Customer;
use App\Models\EmployeeCalendarEvent;
use App\Models\EmployeeGoogleConnection;
use App\Models\FollowUp;
use App\Models\Payment;
use App\Models\PermissionPreset;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

/**
 * Full coverage of the 13-employee-page panel API.
 *
 * The page slugs are the source of truth:
 *
 *   1  employee/dashboard              -> GET /employee/dashboard
 *   2  employee/orders/create          -> POST /employee/orders (+ GET /products)
 *   3  employee/customers              -> GET /employee/customers[/id]
 *   4  employee/payments               -> GET /employee/payments[/id]
 *   5  employee/subscriptions          -> GET /employee/subscriptions[/id]
 *   6  employee/follow-ups             -> GET/POST /employee/follow-ups,
 *                                        PUT/DELETE /employee/follow-ups/{id}
 *   7  employee/subscriptions/expiring -> GET /employee/subscriptions/expiring
 *   8  employee/notifications          -> GET /employee/notifications,
 *                                        POST .../{id}/read, .../read-all
 *   9  employee/calendar               -> GET /employee/calendar,
 *                                        POST .../events, PUT/DELETE .../events/{id},
 *                                        POST /employee/calendar/sync,
 *                                        GET /employee/calendar/connect
 *   10 employee/profile                -> GET /employee/profile,
 *                                        PUT /employee/profile, GET /employee/me
 *   11 employee/customer-issues        -> GET/POST /employee/customer-issues,
 *                                        GET/PUT .../{id}
 *   12 employee/login                  -> POST /employee/login,
 *                                        POST /employee/logout
 *   13 employee/orders                 -> GET /employee/orders/{id} + order actions
 */
class EmployeePanelApiTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected Team $team;

    protected User $employee;

    protected User $otherEmployee;

    protected User $salesEmployee;

    protected string $salesPresetName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create(['team_code' => 'GEM']);
        $this->employee = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherEmployee = User::factory()->create(['team_id' => $this->team->id]);

        // A second, fully-permissioned employee for the permission-gated
        // tests (viewer preset denies follow_ups.create/edit).
        $this->seed(PermissionSeeder::class);
        $this->salesEmployee = User::factory()->create([
            'team_id' => $this->team->id,
            'preset_id' => PermissionPreset::where('slug', 'sales_executive')->value('id'),
        ]);
    }

    protected function employeeHeaders(): array
    {
        return $this->actingAsToken($this->employeeToken($this->salesEmployee));
    }

    protected function otherEmployeeHeaders(): array
    {
        return $this->actingAsToken($this->employeeToken($this->otherEmployee));
    }

    /*
    |----------------------------------------------------------------------
    | 12. employee/login — auth
    |----------------------------------------------------------------------
    */

    public function test_employee_can_login_and_get_a_token(): void
    {
        $response = $this->postJson('/api/v1/employee/login', [
            'login' => $this->employee->username,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token_type', 'token']);
    }

    public function test_employee_login_rejects_wrong_password(): void
    {
        $this->postJson('/api/v1/employee/login', [
            'login' => $this->employee->username,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_admin_or_team_tokens_cannot_use_employee_routes(): void
    {
        $admin = Admin::factory()->create();
        $this->getJson('/api/v1/employee/dashboard', $this->actingAsToken($this->adminToken($admin)))
            ->assertStatus(403);

        $this->getJson('/api/v1/employee/dashboard', $this->actingAsToken($this->teamToken($this->team)))
            ->assertStatus(403);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $token = $this->employeeToken($this->salesEmployee);

        $this->postJson('/api/v1/employee/logout', [], $this->actingAsToken($token))
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        // A revoked token is no longer authenticated.
        $this->getJson('/api/v1/employee/me', $this->actingAsToken($token))
            ->assertStatus(401);
    }

    public function test_me_returns_the_identity_and_permission_flags(): void
    {
        $response = $this->getJson('/api/v1/employee/me', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonStructure(['data', 'has_team_data_access', 'permissions'])
            ->assertJsonPath('data.id', $this->salesEmployee->id);

        // Permissions are a flat map keyed by the dotted permission name, so
        // they are addressed in the raw JSON rather than as a nested path.
        $this->assertTrue($response->json('permissions')['orders.create']);
        $this->assertTrue($response->json('permissions')['follow_ups.create']);
    }

    /*
    |----------------------------------------------------------------------
    | 1. employee/dashboard
    |----------------------------------------------------------------------
    */

    public function test_dashboard_returns_the_full_widget_set(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
            'closing_value' => 100,
        ]);

        $response = $this->getJson('/api/v1/employee/dashboard', $this->employeeHeaders())
            ->assertOk();

        $response->assertJsonStructure([
            'meta' => ['currency_symbol', 'view', 'warning'],
            'summary' => [
                'total_orders', 'closing_value', 'verified_revenue', 'customers_count',
                'active_subscriptions', 'pending_payments_count', 'pending_amount',
                'expiring_subscriptions', 'follow_ups', 'monthly_orders', 'monthly_closing_value',
            ],
            'recent_orders' => ['data'],
            'recent_follow_ups' => ['data'],
            'expiring_subscriptions' => ['data'],
            'recent_notifications' => [],
            'monthly_sales_chart' => [],
        ]);

        $this->assertSame(1, $response->json('summary.total_orders'));
        // JSON decodes a whole-number float as an int, so compare numerically.
        $this->assertSame(100.0, (float) $response->json('summary.closing_value'));
    }

    public function test_team_data_view_downgrades_without_a_grant(): void
    {
        $response = $this->getJson('/api/v1/employee/dashboard?view=team_data', $this->employeeHeaders());

        $response->assertOk();
        // No grant: silently downgraded to my_data with a warning.
        $this->assertSame('my_data', $response->json('meta.view'));
        $this->assertNotNull($response->json('meta.warning'));
    }

    public function test_app_config_mirrors_the_admin_currency_setting(): void
    {
        // Default state: no saved setting -> backend default ('USD').
        $response = $this->getJson('/api/v1/employee/app-config', $this->employeeHeaders())
            ->assertOk();

        $response->assertJsonStructure([
            'success',
            'data' => [
                'currency' => [
                    'code', 'symbol', 'symbol_position', 'decimal_places',
                    'thousands_separator', 'decimal_separator',
                ],
                'currencies',
                'site_name',
            ],
        ]);

        $this->assertSame('USD', $response->json('data.currency.code'));
        $this->assertSame('$', $response->json('data.currency.symbol'));
        $this->assertSame(2, $response->json('data.currency.decimal_places'));

        // Admin switches the currency: the app endpoint follows immediately.
        \App\Models\Setting::setCurrency('EUR');
        \App\Models\Setting::setSiteName('Acme Sales');

        $updated = $this->getJson('/api/v1/employee/app-config', $this->employeeHeaders());
        $this->assertSame('EUR', $updated->json('data.currency.code'));
        $this->assertSame('€', $updated->json('data.currency.symbol'));
        $this->assertSame('Acme Sales', $updated->json('data.site_name'));
    }

    public function test_app_config_requires_an_employee_token(): void
    {
        $this->getJson('/api/v1/employee/app-config')->assertStatus(401);
    }

    /*
    |----------------------------------------------------------------------
    | 2 + 13. employee/orders & employee/orders/create
    |----------------------------------------------------------------------
    */

    public function test_employee_lists_only_their_own_orders_by_default(): void
    {
        $mine = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
        ]);
        SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->otherEmployee->id,
        ]);

        $response = $this->getJson('/api/v1/employee/orders', $this->employeeHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('meta.view', 'my_data');
    }

    public function test_orders_support_pagination_and_filters(): void
    {
        foreach (range(1, 5) as $i) {
            SalesOrder::factory()->create([
                'team_id' => $this->team->id,
                'assigned_employee_id' => $this->salesEmployee->id,
            ]);
        }

        $response = $this->getJson('/api/v1/employee/orders?per_page=2&page=2', $this->employeeHeaders());

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_employee_can_show_their_own_order(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
        ]);

        $this->getJson("/api/v1/employee/orders/{$order->id}", $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonStructure(['data', 'view_only', 'api_key_status', 'whatsapp_share']);
    }

    public function test_employee_creates_an_order_under_their_own_team(): void
    {
        $response = $this->postJson('/api/v1/employee/orders', [
            'customer_name' => 'Grace',
            'closing_value' => 42,
        ], $this->employeeHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.customer_name', 'Grace')
            ->assertJsonPath('data.assigned_employee_id', $this->salesEmployee->id)
            ->assertJsonPath('data.team_id', $this->team->id);

        $this->assertDatabaseHas('sales_orders', [
            'order_number' => $response->json('data.order_number'),
            'team_id' => $this->team->id,
        ]);
    }

    public function test_an_employee_with_no_team_cannot_create_orders(): void
    {
        $solo = User::factory()->create(['team_id' => null]);

        $this->postJson('/api/v1/employee/orders', [
            'customer_name' => 'Grace',
        ], $this->actingAsToken($this->employeeToken($solo)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    public function test_order_actions_gate_on_permissions_and_ownership(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
        ]);

        // Complete
        $this->postJson("/api/v1/employee/orders/{$order->id}/complete", [], $this->employeeHeaders())
            ->assertOk();

        $this->assertSame('confirmed', SalesOrder::find($order->id)->order_status);

        // Resubmit closing value
        $this->postJson("/api/v1/employee/orders/{$order->id}/closing-approval/resubmit", [
            'closing_value' => 40,
            'resubmission_notes' => 'retry',
        ], $this->employeeHeaders())->assertOk();

        // WhatsApp share (no phone -> not eligible, but not an error)
        $this->getJson("/api/v1/employee/orders/{$order->id}/whatsapp-share", $this->employeeHeaders())
            ->assertOk()
            ->assertJsonStructure(['order_id', 'eligible', 'url']);
    }

    /*
    |----------------------------------------------------------------------
    | 3. employee/customers
    |----------------------------------------------------------------------
    */

    public function test_customers_are_scoped_to_the_employee_orders(): void
    {
        $customer = Customer::factory()->create(['team_id' => $this->team->id]);
        $foreign = Customer::factory()->create(['team_id' => Team::factory()->create()->id]);

        SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
            'customer_phone' => $customer->phone,
        ]);
        SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
            'customer_phone' => $foreign->phone,
        ]);

        $response = $this->getJson('/api/v1/employee/customers', $this->employeeHeaders())
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($customer->id, $ids);
        $this->assertContains($foreign->id, $ids);

        $this->getJson("/api/v1/employee/customers/{$customer->id}", $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id);
    }

    /*
    |----------------------------------------------------------------------
    | 4. employee/payments
    |----------------------------------------------------------------------
    */

    public function test_payments_are_scoped_to_the_employee_orders(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
        ]);
        $payment = Payment::factory()->create(['order_id' => $order->id]);
        $foreignPayment = Payment::factory()->create();

        $response = $this->getJson('/api/v1/employee/payments', $this->employeeHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payment->id);

        $this->getJson("/api/v1/employee/payments/{$payment->id}", $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id);

        // A payment whose order is not the employee's is not accessible.
        $this->getJson("/api/v1/employee/payments/{$foreignPayment->id}", $this->employeeHeaders())
            ->assertStatus(422);
    }

    /*
    |----------------------------------------------------------------------
    | 5 + 7. employee/subscriptions & expiring
    |----------------------------------------------------------------------
    */

    public function test_subscriptions_are_scoped_to_the_employee_orders(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
        ]);
        $subscription = Subscription::factory()->create([
            'order_id' => $order->id,
            'team_id' => $this->team->id,
            'status' => 'active',
            'expires_at' => now()->addDays(5),
        ]);

        $this->getJson('/api/v1/employee/subscriptions', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/employee/subscriptions/expiring', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $subscription->id);

        $this->getJson("/api/v1/employee/subscriptions/{$subscription->id}", $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id);
    }

    /*
    |----------------------------------------------------------------------
    | 6. employee/follow-ups
    |----------------------------------------------------------------------
    */

    public function test_follow_ups_are_scoped_to_the_employee(): void
    {
        $customer = Customer::factory()->paying()->create(['team_id' => $this->team->id]);

        $mine = FollowUp::factory()->create([
            'customer_id' => $customer->id,
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
        ]);
        FollowUp::factory()->create([
            'customer_id' => $customer->id,
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->otherEmployee->id,
        ]);

        $this->getJson('/api/v1/employee/follow-ups', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->getJson("/api/v1/employee/follow-ups/{$mine->id}", $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);
    }

    public function test_employee_creates_a_follow_up_on_a_verified_customer(): void
    {
        $customer = Customer::factory()->paying()->create(['team_id' => $this->team->id]);
        // A follow-up picker only offers the customers of the employee's own
        // orders, so link the customer through one.
        SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
            'customer_phone' => $customer->phone,
            'customer_name' => $customer->name,
        ]);

        $response = $this->postJson('/api/v1/employee/follow-ups', [
            'customer_id' => $customer->id,
            'title' => 'Renewal check-in',
            'follow_up_type' => 'subscription_renewal',
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
        ], $this->employeeHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Renewal check-in')
            ->assertJsonPath('data.assigned_employee_id', $this->salesEmployee->id)
            ->assertJsonPath('data.status', 'scheduled');
    }

    public function test_follow_up_complete_reschedule_and_cancel(): void
    {
        $customer = Customer::factory()->paying()->create(['team_id' => $this->team->id]);
        SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
            'customer_phone' => $customer->phone,
            'customer_name' => $customer->name,
        ]);
        $followUp = FollowUp::factory()->create([
            'customer_id' => $customer->id,
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
        ]);

        $this->putJson("/api/v1/employee/follow-ups/{$followUp->id}", [
            'action' => 'reschedule',
            'scheduled_at' => now()->addDays(7)->toIso8601String(),
        ], $this->employeeHeaders())->assertOk();

        $this->putJson("/api/v1/employee/follow-ups/{$followUp->id}", [
            'action' => 'complete',
            'outcome' => 'renewed',
        ], $this->employeeHeaders())->assertOk();

        $this->assertSame('completed', $followUp->fresh()->status);

        $other = FollowUp::factory()->create([
            'customer_id' => $customer->id,
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
        ]);

        $this->deleteJson("/api/v1/employee/follow-ups/{$other->id}", [], $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    /*
    |----------------------------------------------------------------------
    | 8. employee/notifications
    |----------------------------------------------------------------------
    */

    public function test_notifications_list_mark_read_and_read_all(): void
    {
        // The notifications primary key is a UUID, so the generated id is
        // tracked in a variable — insertGetId returns the auto-increment
        // counter on SQLite, not the generated UUID.
        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => \App\Notifications\GeneralOrderNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->salesEmployee->id,
            'data' => json_encode(['title' => 'Hi', 'message' => 'Test', 'action' => 'general']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/employee/notifications', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.id', $id);

        // Mark one read
        $this->postJson("/api/v1/employee/notifications/{$id}/read", [], $this->employeeHeaders())
            ->assertOk();

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->value('read_at'));

        // read-all reports the count of previously-unread items
        $secondId = (string) \Illuminate\Support\Str::uuid();
        DB::table('notifications')->insert([
            'id' => $secondId,
            'type' => \App\Notifications\GeneralOrderNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->salesEmployee->id,
            'data' => json_encode(['title' => 'X']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/employee/notifications/read-all', [], $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'All notifications marked read (1 unread).');

        $this->assertNotNull(DB::table('notifications')->where('id', $secondId)->value('read_at'));
    }

    /*
    |----------------------------------------------------------------------
    | 9. employee/calendar
    |----------------------------------------------------------------------
    */

    public function test_calendar_index_reports_the_connection_state(): void
    {
        $this->getJson('/api/v1/employee/calendar', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('connection.connected', false)
            ->assertJsonPath('data', []);
    }

    public function test_calendar_event_create_lists_and_updates(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::sequence()
                ->push(['id' => 'evt-1'])
                ->push(['id' => 'evt-1']),
        ]);

        $employee = $this->salesEmployee;
        $this->connectEmployeeGoogleAccount($employee);

        $created = $this->postJson('/api/v1/employee/calendar/events', [
            'summary' => 'Client call',
            'start_at' => now()->addDays(2)->toIso8601String(),
        ], $this->employeeHeaders())->assertCreated();

        $eventId = $created->json('data.id');

        $this->getJson('/api/v1/employee/calendar', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $eventId);

        $this->putJson("/api/v1/employee/calendar/events/{$eventId}", [
            'summary' => 'Client call (moved)',
        ], $this->employeeHeaders())->assertOk();

        $this->assertSame('Client call (moved)', EmployeeCalendarEvent::find($eventId)->summary);
    }

    public function test_calendar_event_delete_marks_the_row_deleted(): void
    {
        Http::fake([
            'googleapis.com/*' => Http::sequence()
                ->push(['id' => 'evt-2'])
                ->push(null, 404),
        ]);

        $this->connectEmployeeGoogleAccount($this->salesEmployee);

        $created = $this->postJson('/api/v1/employee/calendar/events', [
            'summary' => 'To be deleted',
            'start_at' => now()->addDays(3)->toIso8601String(),
        ], $this->employeeHeaders())->assertCreated();

        $eventId = $created->json('data.id');

        $this->deleteJson("/api/v1/employee/calendar/events/{$eventId}", [], $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('meta.deleted', true);

        $this->assertSame('deleted', EmployeeCalendarEvent::find($eventId)->status);
    }

    public function test_calendar_sync_reports_idempotent_results(): void
    {
        Http::fake(['googleapis.com/*' => Http::response(['id' => 'evt-3'])]);
        $this->connectEmployeeGoogleAccount($this->salesEmployee);

        $this->postJson('/api/v1/employee/calendar/sync', [], $this->employeeHeaders())
            ->assertOk()
            ->assertJsonStructure(['meta' => ['events_synced', 'retried', 'failed', 'connected']])
            ->assertJsonPath('meta.connected', true);
    }

    public function test_calendar_connect_returns_an_oauth_url(): void
    {
        config([
            'google.client_id' => 'test-client-id',
            'google.client_secret' => 'test-client-secret',
            'google.redirect_uri' => 'http://localhost:8000/api/v1/google/calendar/callback',
        ]);

        $this->getJson('/api/v1/employee/calendar/connect', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonStructure(['url', 'state', 'redirect_uri'])
            ->assertJsonPath('redirect_uri', 'http://localhost:8000/api/v1/google/calendar/callback');
    }

    protected function connectEmployeeGoogleAccount(User $employee): void
    {
        EmployeeGoogleConnection::create([
            'employee_id' => $employee->id,
            'google_email' => 'employee@gmail.com',
            'calendar_id' => 'primary',
            'access_token' => 'ya29.fake',
            'refresh_token' => '1//fake',
            'access_token_expires_at' => now()->addHour(),
            'status' => 'connected',
            'connected_at' => now(),
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | 10. employee/profile
    |----------------------------------------------------------------------
    */

    public function test_profile_returns_the_identity_document(): void
    {
        $this->getJson('/api/v1/employee/profile', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $this->salesEmployee->id)
            ->assertJsonPath('data.team.id', $this->team->id);
    }

    public function test_profile_update_changes_contact_fields_only(): void
    {
        $this->putJson('/api/v1/employee/profile', [
            'name' => 'Renamed',
            'phone' => '+15550001',
        ], $this->employeeHeaders())->assertOk();

        $this->assertSame('Renamed', $this->salesEmployee->fresh()->name);
        $this->assertSame('+15550001', $this->salesEmployee->fresh()->phone);
        // Role is admin-managed, never updatable by the employee.
        $this->assertSame('employee', $this->salesEmployee->fresh()->role);
    }

    public function test_profile_password_change_revokes_other_tokens(): void
    {
        $tokenA = $this->employeeToken($this->salesEmployee);
        $tokenB = $this->employeeToken($this->salesEmployee);

        $this->putJson('/api/v1/employee/profile', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ], $this->actingAsToken($tokenA))->assertOk();

        // The performing token stays valid…
        $this->getJson('/api/v1/employee/me', $this->actingAsToken($tokenA))->assertOk();

        // …every other token is revoked.
        $this->getJson('/api/v1/employee/me', $this->actingAsToken($tokenB))
            ->assertStatus(401);
    }

    /*
    |----------------------------------------------------------------------
    | 11. employee/customer-issues
    |----------------------------------------------------------------------
    */

    public function test_customer_issue_create_show_and_update(): void
    {
        $customer = Customer::factory()->paying()->create(['team_id' => $this->team->id, 'phone' => '+919876543210', 'normalized_phone' => '+919876543210']);
        SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
            'customer_phone' => $customer->phone,
        ]);

        $created = $this->postJson('/api/v1/employee/customer-issues', [
            'customer_id' => $customer->id,
            'issue_title' => 'Slow loading',
            'issue_description' => 'The dashboard takes a very long time.',
        ], $this->employeeHeaders())->assertCreated();

        $issueId = $created->json('data.id');
        $this->assertSame('still_facing', $created->json('data.status'));

        $this->getJson("/api/v1/employee/customer-issues/{$issueId}", $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $issueId)
            ->assertJsonPath('data.employee_id', $this->salesEmployee->id);

        $this->putJson("/api/v1/employee/customer-issues/{$issueId}", [
            'status' => 'fixed',
            'update_note' => 'Resolved on our side.',
        ], $this->employeeHeaders())->assertOk();

        $this->assertSame('fixed', CrmCustomerIssue::find($issueId)->status);
    }

    public function test_an_employee_cannot_read_another_employees_issue(): void
    {
        $customer = Customer::factory()->paying()->create(['team_id' => $this->team->id, 'phone' => '+919876543210', 'normalized_phone' => '+919876543210']);
        SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
            'customer_phone' => $customer->phone,
        ]);

        $created = $this->postJson('/api/v1/employee/customer-issues', [
            'customer_id' => $customer->id,
            'issue_title' => 'X',
            'issue_description' => 'Y',
        ], $this->employeeHeaders())->assertCreated();

        $this->getJson("/api/v1/employee/customer-issues/{$created->json('data.id')}", $this->otherEmployeeHeaders())
            ->assertStatus(422);

        $this->putJson("/api/v1/employee/customer-issues/{$created->json('data.id')}", [
            'status' => 'fixed',
        ], $this->otherEmployeeHeaders())->assertStatus(422);
    }

    public function test_customer_issue_index_returns_summary_counts(): void
    {
        $customer = Customer::factory()->paying()->create(['team_id' => $this->team->id, 'phone' => '+919876543211', 'normalized_phone' => '+919876543211']);
        SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->salesEmployee->id,
            'customer_phone' => $customer->phone,
        ]);

        CrmCustomerIssue::create([
            'employee_id' => $this->salesEmployee->id,
            'customer_id' => $customer->id,
            'issue_title' => 'A',
            'issue_description' => 'a',
            'status' => 'still_facing',
            'issue_date' => now()->toDateString(),
        ]);

        $this->getJson('/api/v1/employee/customer-issues', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total_issues', 1)
            ->assertJsonPath('meta.still_facing_issues', 1);
    }

    /*
    |----------------------------------------------------------------------
    | 2 (helper): products picker
    |----------------------------------------------------------------------
    */

    public function test_active_products_are_listed_for_order_creation(): void
    {
        Product::factory()->create();
        Product::factory()->inactive()->create();

        $this->getJson('/api/v1/products', $this->employeeHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'products');
    }
}
