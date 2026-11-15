<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\BeamsService;
use App\Services\NotificationHubService;
use App\Services\PaymentService;
use App\Services\SubscriptionActivator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationsHubTest extends TestCase
{
    use RefreshDatabase;

    protected Team $team;
    protected User $employee;
    protected Admin $admin;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create([
            'team_name' => 'Support Squad',
            'team_code' => 'SUP',
        ]);

        $this->employee = User::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'active',
            'password' => bcrypt('password123'),
        ]);

        $this->admin = Admin::factory()->create([
            'email' => 'admin_notify@leadx.com',
            'password' => bcrypt('adminpass'),
        ]);

        $this->product = Product::factory()->create([
            'status' => 'active',
            'price' => 1000.00,
            'minimum_closing_price' => 800.00,
            'api_key_required' => false,
        ]);
    }

    public function test_employee_receives_database_notification_on_order_creation(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 950.00,
            'product_id' => $this->product->id,
        ]);

        app(NotificationHubService::class)->notifyOrderCreated($order);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->employee->id,
        ]);

        $notification = $this->employee->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals('New Order Attributed', $notification->data['title']);
        $this->assertStringContainsString($order->order_number, $notification->data['message']);
    }

    public function test_closing_value_approval_decision_dispatches_notification(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 700.00,
            'closing_value_status' => 'Requested',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post("/admin/orders/{$order->id}/closing-approval/accept", [
                'notes' => 'Special promotion authorized.',
            ]);

        $response->assertSessionHas('status');

        $notification = $this->employee->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Closing Value Approved', $notification->data['title']);
    }

    public function test_payment_verification_notifies_employee(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 1000.00,
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 1000.00,
            'payment_status' => 'pending',
        ]);

        app(PaymentService::class)->verify($payment, $this->admin->id, 'Bank transfer cleared');

        $this->assertTrue($payment->fresh()->isVerified());

        $hasPaymentNotification = $this->employee->notifications()
            ->where('data->action', 'payment_verified')
            ->exists();

        $this->assertTrue($hasPaymentNotification);
    }

    public function test_subscription_activation_notifies_employee(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 500.00,
            'payment_status' => 'paid',
            'closing_value_status' => 'Accepted',
            'api_key_required_snapshot' => false,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 500.00,
            'payment_status' => 'paid',
            'verified_at' => now(),
            'verified_by_admin_id' => $this->admin->id,
        ]);

        $subscription = app(SubscriptionActivator::class)->maybeActivate($order, $this->admin->id);
        $this->assertNotNull($subscription);
        $this->assertEquals('active', $subscription->status);

        $hasSubNotification = $this->employee->notifications()
            ->where('data->action', 'subscription_activated')
            ->exists();

        $this->assertTrue($hasSubNotification);
    }

    public function test_order_cancellation_notifies_employee(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => 500.00,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post("/admin/orders/{$order->id}/cancel", [
                'cancellation_reason' => 'Customer requested refund before provisioning.',
            ]);

        $hasCancelNotification = $this->employee->notifications()
            ->where('data->action', 'cancelled')
            ->exists();

        $this->assertTrue($hasCancelNotification);
    }

    public function test_employee_can_view_notifications_inbox(): void
    {
        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
        ]);

        app(NotificationHubService::class)->notifyOrderCreated($order);

        $this->assertEquals(1, $this->employee->unreadNotifications()->count());

        $response = $this->actingAs($this->employee)
            ->get(route('employee.notifications'));

        $response->assertOk();
        $response->assertSee('New Order Attributed');
        $response->assertSee($order->order_number);

        // Accessing notifications marks unread notifications as read
        $this->assertEquals(0, $this->employee->fresh()->unreadNotifications()->count());
    }

    public function test_failing_pusher_beams_does_not_break_execution(): void
    {
        // Mock beams to throw an exception
        $mockBeams = $this->createMock(BeamsService::class);
        $mockBeams->method('publishWebNotification')
            ->willThrowException(new \RuntimeException('Pusher Beams Gateway Timeout'));

        $hub = new NotificationHubService($mockBeams, app(\App\Services\EmailService::class));

        $order = SalesOrder::factory()->create([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
        ]);

        // Must not throw any exception
        $hub->notifyOrderCreated($order);

        // Database notification was still written safely
        $this->assertEquals(1, $this->employee->notifications()->count());
    }
}
