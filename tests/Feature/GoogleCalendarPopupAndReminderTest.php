<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EmployeeGoogleConnection;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\Google\SubscriptionCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarPopupAndReminderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected Team $team;
    protected Product $product;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create([
            'team_name' => 'Alpha Team',
            'team_code' => 'ALP',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->employee = User::factory()->create([
            'role' => 'employee',
            'team_id' => $this->team->id,
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'name' => 'Prem Kumar',
            'phone' => '+918608239201',
            'email' => 'prem@example.com',
            'team_id' => $this->team->id,
            'created_by' => $this->employee->id,
        ]);

        $this->product = Product::create([
            'name' => 'Claude 5x Plan',
            'sku' => 'CLAUDE-5X',
            'price' => 5499.00,
            'minimum_closing_price' => 5000.00,
            'duration' => '30d',
            'is_active' => true,
        ]);

        Config::set('google.client_id', 'test-client-id.apps.googleusercontent.com');
        Config::set('google.client_secret', 'test-client-secret');
        Config::set('google.redirect_uri', 'http://127.0.0.1:8000/api/v1/google/calendar/callback');
    }

    public function test_sales_order_generates_google_calendar_popup_url(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'ALP-2026-0001',
            'team_id' => $this->team->id,
            'user_id' => $this->employee->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'customer_email' => $this->customer->email,
            'product_name' => $this->product->name,
            'product_name_snapshot' => $this->product->name,
            'product_value' => 5499.00,
            'closing_value' => 5000.00,
            'duration' => '30d',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $url = $order->googleCalendarUrl();

        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?', $url);
        $this->assertStringContainsString('action=TEMPLATE', $url);
        $this->assertStringContainsString(urlencode('LeadX Expiry: Prem Kumar (ALP-2026-0001)'), $url);
        $this->assertStringContainsString(urlencode('36 hours before expiry'), $url);
    }

    public function test_subscription_calendar_event_payload_includes_36_hour_reminder_overrides(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'ALP-2026-0002',
            'team_id' => $this->team->id,
            'user_id' => $this->employee->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'customer_email' => $this->customer->email,
            'product_name' => $this->product->name,
            'product_value' => 5499.00,
            'closing_value' => 5000.00,
            'duration' => '30d',
            'order_status' => 'active',
            'payment_status' => 'paid',
        ]);

        $subscription = Subscription::create([
            'order_id' => $order->id,
            'team_id' => $this->team->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->product->id,
            'status' => 'active',
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
            'amount_paid' => 5000.00,
            'price_snapshot' => 5000.00,
        ]);

        // Connect employee calendar
        EmployeeGoogleConnection::create([
            'employee_id' => $this->employee->id,
            'google_email' => 'employee@example.com',
            'calendar_id' => 'primary',
            'status' => 'connected',
            'access_token' => 'fake-access-token',
            'refresh_token' => 'fake-refresh-token',
            'access_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => function ($request) {
                $payload = $request->data();
                $reminders = $payload['reminders'] ?? [];
                $overrides = $reminders['overrides'] ?? [];

                // Assert 36 hours (2160 minutes) for popup and email
                $has36hPopup = false;
                $has36hEmail = false;
                foreach ($overrides as $override) {
                    if ($override['minutes'] === 2160 && $override['method'] === 'popup') {
                        $has36hPopup = true;
                    }
                    if ($override['minutes'] === 2160 && $override['method'] === 'email') {
                        $has36hEmail = true;
                    }
                }

                if ($has36hPopup && $has36hEmail) {
                    return Http::response(['id' => 'google-event-36h-123'], 200);
                }

                return Http::response(['error' => 'Missing 36-hour reminder overrides'], 400);
            },
        ]);

        $service = app(SubscriptionCalendarService::class);
        $result = $service->syncCreate($subscription);

        $this->assertTrue($result['created']);
        $this->assertEquals('google-event-36h-123', $result['event_id']);
        $this->assertEquals('synced', $subscription->fresh()->calendar_sync_status);
    }

    public function test_admin_order_detail_view_renders_google_calendar_popup_button(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'ALP-2026-0003',
            'team_id' => $this->team->id,
            'user_id' => $this->employee->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'customer_email' => $this->customer->email,
            'product_name' => $this->product->name,
            'product_value' => 5499.00,
            'closing_value' => 5000.00,
            'duration' => '30d',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.orders.show', $order));

        $response->assertStatus(200);
        $response->assertSee('Add to Google Calendar (Popup)');
        $response->assertSee('36h Expiry Alert');
    }

    public function test_employee_order_detail_view_renders_google_calendar_popup_button(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'ALP-2026-0004',
            'team_id' => $this->team->id,
            'user_id' => $this->employee->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'customer_email' => $this->customer->email,
            'product_name' => $this->product->name,
            'product_value' => 5499.00,
            'closing_value' => 5000.00,
            'duration' => '30d',
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)->get(route('employee.orders.show', $order));

        $response->assertStatus(200);
        $response->assertSee('Add to Google Calendar (Popup)');
        $response->assertSee('36h Alert Preset');
    }

    public function test_employee_web_calendar_page_displays_36_hour_configuration(): void
    {
        $response = $this->actingAs($this->employee)->get(route('employee.calendar'));

        $response->assertStatus(200);
        $response->assertSee('36-Hour Advance Expiry Notification Configured');
        $response->assertSee(route('employee.calendar.connect'));
    }

    public function test_employee_calendar_web_connect_redirects_to_google_oauth(): void
    {
        $response = $this->actingAs($this->employee)->get(route('employee.calendar.connect'));

        $response->assertStatus(302);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $response->headers->get('Location'));
    }

    public function test_employee_calendar_web_disconnect(): void
    {
        EmployeeGoogleConnection::create([
            'employee_id' => $this->employee->id,
            'google_email' => 'employee@example.com',
            'calendar_id' => 'primary',
            'status' => 'connected',
            'access_token' => 'fake-access-token',
            'access_token_expires_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($this->employee)->post(route('employee.calendar.disconnect'));

        $response->assertRedirect(route('employee.calendar'));
        $response->assertSessionHas('status', 'Google Calendar disconnected.');
        $this->assertDatabaseHas('employee_google_connections', [
            'employee_id' => $this->employee->id,
            'status' => 'disconnected',
            'access_token' => null,
        ]);
    }
}
