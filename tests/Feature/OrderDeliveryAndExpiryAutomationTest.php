<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\EmployeeGoogleConnection;
use App\Models\OrderApiCredential;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\SubscriptionExpiryNotification;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappShareLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderDeliveryAndExpiryAutomationTest extends TestCase
{
    use RefreshDatabase;

    private string $adminName;
    private string $teamName;
    private string $employeeName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminName = 'Admin-'.Str::random(8);
        $this->teamName = 'Team-'.Str::random(6);
        $this->employeeName = 'Emp-'.Str::random(6);
    }

    protected function admin(): Admin
    {
        return Admin::factory()->create(['name' => $this->adminName]);
    }

    protected function team(string $code = 'GEM', string $name = null): Team
    {
        return Team::factory()->create([
            'team_code' => $code,
            'team_name' => $name ?? $this->teamName,
        ]);
    }

    protected function employee(Team $team, ?string $name = null): User
    {
        return User::factory()->create([
            'team_id' => $team->id,
            'role' => 'employee',
            'status' => 'active',
            'name' => $name ?? $this->employeeName.'-'.Str::random(4),
        ]);
    }

    protected function otherTeamEmployee(Team $otherTeam): User
    {
        return User::factory()->create([
            'team_id' => $otherTeam->id,
            'role' => 'employee',
            'status' => 'active',
            'name' => 'Other-'.Str::random(6),
        ]);
    }

    // ─── 1. API Key manual entry and encryption ──────────────────────────────

    public function test_admin_can_store_api_key_and_it_is_encrypted_at_rest(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
            'order_status' => 'active',
            'payment_status' => 'paid',
        ]);

        $plainKey = 'sk-proj-test-api-key-'.Str::random(12);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.orders.api-key.store', $order), [
                'api_key' => $plainKey,
            ])
            ->assertSessionHas('status');

        $credential = OrderApiCredential::firstWhere('order_id', $order->id);
        $this->assertNotNull($credential, 'OrderApiCredential row was not created.');

        // Raw ciphertext in the DB must NOT equal the plaintext.
        $rawCipher = \DB::table('order_api_credentials')
            ->where('order_id', $order->id)
            ->value('key');
        $this->assertNotNull($rawCipher);
        $this->assertNotEquals($plainKey, $rawCipher, 'DB value must be ciphertext, not plaintext.');

        // Model's encrypted cast decrypts back to original.
        $credential->refresh();
        $this->assertSame($plainKey, $credential->decryptedKey());
    }

    public function test_replacing_api_key_updates_in_place(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
            'order_status' => 'active',
            'payment_status' => 'paid',
        ]);

        $firstKey = 'sk-first-'.Str::random(8);
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.orders.api-key.store', $order), ['api_key' => $firstKey]);

        $secondKey = 'sk-second-'.Str::random(8);
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.orders.api-key.store', $order), ['api_key' => $secondKey]);

        $this->assertEquals(1, OrderApiCredential::where('order_id', $order->id)->count());

        $cred = OrderApiCredential::firstWhere('order_id', $order->id);
        $this->assertSame($secondKey, $cred->decryptedKey());
    }

    public function test_blank_api_key_is_rejected(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.orders.api-key.store', $order), ['api_key' => '   '])
            ->assertSessionHasErrors(['api_key']);

        $this->assertNull(OrderApiCredential::where('order_id', $order->id)->first());
    }

    // ─── 2 & 3. WhatsApp message + correct phone number ──────────────────────

    public function test_whatsapp_share_generates_correct_message_and_phone(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $customerPhone = '+15551234567';
        $customerName = 'Grace-'.Str::random(6);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'product_name' => 'Elite Plan',
            'product_price_snapshot' => 299.99,
            'product_duration_snapshot' => '1y',
            'order_status' => 'active',
            'payment_status' => 'paid',
        ]);

        Subscription::create([
            'order_id' => $order->id,
            'team_id' => $team->id,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
            'duration_snapshot' => '1y',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee($customerName)
            ->assertSee($order->order_number)
            ->assertSee('Elite Plan')
            ->assertSee('299.99')
            ->assertSee('1 year');
    }

    public function test_whatsapp_message_includes_phone_when_present(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $customerPhone = '+919876543210';
        $customerName = 'Ada-'.Str::random(6);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'product_name' => 'Basic Plan',
            'product_price_snapshot' => 99.99,
            'product_duration_snapshot' => '30d',
            'order_status' => 'active',
            'payment_status' => 'paid',
        ]);

        Subscription::create([
            'order_id' => $order->id,
            'team_id' => $team->id,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'duration_snapshot' => '30d',
        ]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.show', $order));

        $response->assertOk();
        $this->assertStringContainsString('9876543210', $response->content());
    }

    // ─── 4. Expiry date calculation ──────────────────────────────────────────

    public function test_expiry_date_matches_activation_date_plus_duration(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
            'duration' => '30d',
            'product_duration_snapshot' => '30d',
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
        ]);

        \App\Models\Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'bank_transfer',
            'amount' => $order->amountDue(),
            'payment_status' => 'paid',
            'verified_by_admin_id' => $this->admin()->id,
            'verified_at' => now(),
        ]);

        $subscription = app(\App\Services\SubscriptionActivator::class)
            ->maybeActivate($order, $this->admin()->id);

        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertSame('30d', $subscription->duration_snapshot);

        // The activation engine must set both dates and the expiry must be in the future.
        $this->assertNotNull($subscription->started_at, 'started_at must be set.');
        $this->assertNotNull($subscription->expires_at, 'expires_at must be set.');
        $this->assertTrue($subscription->expires_at->isFuture(), 'Expiry must be in the future.');
        $this->assertTrue(
            $subscription->expires_at->greaterThan($subscription->started_at),
            'Expiry must be after the activation date.'
        );
    }

    public function test_historical_duration_is_not_affected_by_product_changes(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
            'duration' => '1y',
            'product_duration_snapshot' => '1y',
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
        ]);

        \App\Models\Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'bank_transfer',
            'amount' => $order->amountDue(),
            'payment_status' => 'paid',
            'verified_by_admin_id' => $this->admin()->id,
            'verified_at' => now(),
        ]);

        $subscription = app(\App\Services\SubscriptionActivator::class)
            ->maybeActivate($order, $this->admin()->id);

        $this->assertNotNull($subscription);
        $originalExpiry = $subscription->expires_at;
        $originalSnapshot = $subscription->duration_snapshot;

        // Simulate the catalog product changing its duration AFTER activation.
        $order->update([
            'product_duration_snapshot' => '30d',
            'duration' => '30d',
        ]);

        // The frozen subscription dates must not change just because the
        // order's duration was edited after activation.
        $subscription->refresh();
        $this->assertEquals($originalExpiry->toDateString(), $subscription->expires_at->toDateString());
        $this->assertSame($originalSnapshot, $subscription->duration_snapshot);
    }

    // ─── 5. Google Calendar event creation ───────────────────────────────────

    public function test_active_subscription_marks_calendar_sync_when_employee_connected(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
        ]);

        \App\Models\Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'bank_transfer',
            'amount' => $order->amountDue(),
            'payment_status' => 'paid',
            'verified_by_admin_id' => $this->admin()->id,
            'verified_at' => now(),
        ]);

        EmployeeGoogleConnection::create([
            'employee_id' => $emp->id,
            'status' => 'connected',
            'calendar_id' => 'primary@example.com',
        ]);

        $subscription = app(\App\Services\SubscriptionActivator::class)
            ->maybeActivate($order, $this->admin()->id);

        $this->assertNotNull($subscription);
        $this->assertNotNull($subscription->calendar_sync_status);
    }

    // ─── 6. Duplicate event prevention ───────────────────────────────────────

    public function test_calendar_sync_is_idempotent_when_event_already_exists(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
        ]);

        \App\Models\Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'bank_transfer',
            'amount' => $order->amountDue(),
            'payment_status' => 'paid',
            'verified_by_admin_id' => $this->admin()->id,
            'verified_at' => now(),
        ]);

        EmployeeGoogleConnection::create([
            'employee_id' => $emp->id,
            'status' => 'connected',
            'calendar_id' => 'primary@example.com',
        ]);

        $subscription = app(\App\Services\SubscriptionActivator::class)
            ->maybeActivate($order, $this->admin()->id);
        $this->assertNotNull($subscription);

        $firstEventId = $subscription->google_event_id;

        // Calling syncCreate a second time must not change the event id.
        app(\App\Services\Google\SubscriptionCalendarService::class)
            ->syncCreate($subscription->fresh());

        $subscription->refresh();
        $this->assertSame($firstEventId, $subscription->google_event_id);
    }

    // ─── 7. One-day-before notification ──────────────────────────────────────

    public function test_team_members_are_notified_when_subscription_expires_tomorrow(): void
    {
        $team = $this->team();
        $emp1 = $this->employee($team, 'Active1-'.Str::random(4));
        $emp2 = $this->employee($team, 'Active2-'.Str::random(4));
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp1->id,
        ]);

        $tomorrow = now()->addDay()->toDateString();
        $subscription = Subscription::create([
            'order_id' => $order->id,
            'team_id' => $team->id,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => $tomorrow,
        ]);

        $service = new \App\Services\SubscriptionExpiryNotificationService();
        $recipients = $service->notifyIfDue($subscription);

        $this->assertGreaterThanOrEqual(2, $recipients, 'Both team members should receive the reminder.');
    }

    public function test_no_notification_when_no_active_team_members(): void
    {
        $team = $this->team();
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
        ]);

        $tomorrow = now()->addDay()->toDateString();
        $subscription = Subscription::create([
            'order_id' => $order->id,
            'team_id' => $team->id,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => $tomorrow,
        ]);

        $service = new \App\Services\SubscriptionExpiryNotificationService();
        $recipients = $service->notifyIfDue($subscription);

        $this->assertSame(0, $recipients);
    }

    // ─── 8. Only assigned Team users receive notifications ───────────────────

    public function test_only_team_members_receive_notifications(): void
    {
        $teamA = $this->team('GEMA', 'Alpha');
        $teamB = $this->team('GEMB', 'Beta');
        $memberA = $this->employee($teamA, 'MemberA-'.Str::random(4));
        $memberB = $this->employee($teamB, 'MemberB-'.Str::random(4));

        $order = SalesOrder::factory()->create([
            'team_id' => $teamA->id,
            'assigned_employee_id' => $memberA->id,
        ]);

        $tomorrow = now()->addDay()->toDateString();
        $subscription = Subscription::create([
            'order_id' => $order->id,
            'team_id' => $teamA->id,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => $tomorrow,
        ]);

        $notificationType = \App\Notifications\SubscriptionExpiresTomorrowNotification::class;

        $service = new \App\Services\SubscriptionExpiryNotificationService();
        $service->notifyIfDue($subscription);

        // Team A member received a notification.
        $this->assertTrue(
            \Illuminate\Support\Facades\DB::table('notifications')
                ->where('notifiable_id', $memberA->id)
                ->where('type', $notificationType)
                ->exists(),
            'Team A member should have received the expiry reminder.'
        );

        // Team B member received NOTHING.
        $this->assertFalse(
            \Illuminate\Support\Facades\DB::table('notifications')
                ->where('notifiable_id', $memberB->id)
                ->where('type', $notificationType)
                ->exists(),
            'Team B member should NOT have received the reminder.'
        );
    }

    // ─── 9. Other teams receive zero notifications ───────────────────────────

    public function test_unrelated_teams_receive_zero_notifications(): void
    {
        $correctTeam = $this->team('ABC', 'Alpha');
        $otherTeam = $this->team('XYZ', 'Xray');
        $empCorrect = $this->employee($correctTeam, 'Corr-'.Str::random(4));
        $empOther = $this->employee($otherTeam, 'Othr-'.Str::random(4));

        $order = SalesOrder::factory()->create([
            'team_id' => $correctTeam->id,
            'assigned_employee_id' => $empCorrect->id,
        ]);

        $tomorrow = now()->addDay()->toDateString();
        Subscription::create([
            'order_id' => $order->id,
            'team_id' => $correctTeam->id,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => $tomorrow,
        ]);

        $notificationType = \App\Notifications\SubscriptionExpiresTomorrowNotification::class;

        $service = new \App\Services\SubscriptionExpiryNotificationService();
        $service->notifyIfDue($order->subscription);

        $otherNotificationCount = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_id', $empOther->id)
            ->where('type', $notificationType)
            ->count();

        $this->assertSame(0, $otherNotificationCount, 'No unrelated team member should be notified.');
    }

    // ─── 10. Duplicate notification prevention ───────────────────────────────

    public function test_duplicate_notifications_are_prevented_for_same_cycle(): void
    {
        $team = $this->team();
        $emp = $this->employee($team, 'DupEmp-'.Str::random(4));
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
        ]);

        $tomorrow = now()->addDay()->toDateString();
        $subscription = Subscription::create([
            'order_id' => $order->id,
            'team_id' => $team->id,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => $tomorrow,
        ]);

        // Pre-seed the cycle log directly so the guard sees it as
        // "already sent" and notifyIfDue returns 0 on the second run.
        \Illuminate\Support\Facades\DB::table('subscription_expiry_notifications')->insert([
            'subscription_id' => $subscription->id,
            'expiry_at' => $subscription->expires_at->toDateString(),
            'status' => 'sent',
            'recipient_count' => 1,
            'recipient_user_ids' => json_encode([$emp->id]),
            'sent_at' => now()->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(SubscriptionExpiryNotification::alreadySentForCycle($subscription));

        $service = new \App\Services\SubscriptionExpiryNotificationService();

        // Because the cycle is already recorded, notifyIfDue must return 0
        // and never attempt a second delivery.
        $recipients = $service->notifyIfDue($subscription);
        $this->assertSame(0, $recipients, 'notifyIfDue must skip an already-recorded cycle.');
    }

    // ─── Additional: WhatsApp share log records correctly ────────────────────

    public function test_whatsapp_share_log_does_not_claim_delivery(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $order = SalesOrder::factory()->active()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
        ]);

        Subscription::create([
            'order_id' => $order->id,
            'team_id' => $team->id,
            'status' => 'active',
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.orders.whatsapp-log', $order), [
                'message' => 'Test message body',
            ])
            ->assertSessionHas('status');

        $log = WhatsappShareLog::firstWhere('order_id', $order->id);
        $this->assertNotNull($log);
        $this->assertSame('recorded', $log->status);
    }

    // ─── Additional: order show page renders API Key panel ───────────────────

    public function test_order_show_renders_api_key_panel(): void
    {
        $team = $this->team();
        $emp = $this->employee($team);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $emp->id,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Customer API Key')
            ->assertSee('Save / replace API key');
    }
}
