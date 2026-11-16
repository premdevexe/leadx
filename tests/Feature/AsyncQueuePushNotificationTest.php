<?php

namespace Tests\Feature;

use App\Jobs\SendWebPushNotificationJob;
use App\Models\Admin;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\BeamsService;
use App\Services\NotificationHubService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AsyncQueuePushNotificationTest extends TestCase
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
            'team_name' => 'Queue Test Team',
            'team_code' => 'QTT',
        ]);

        $this->employee = User::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'active',
            'role' => 'employee',
        ]);

        $this->admin = Admin::factory()->create([
            'email' => 'admin_queue@leadx.com',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Queue Pro Plan',
            'status' => 'active',
            'price' => 500.00,
            'minimum_closing_price' => 400.00,
            'duration' => '1y',
        ]);
    }

    /**
     * Test 1: Order creation dispatches SendWebPushNotificationJob to the queue.
     */
    public function test_order_creation_dispatches_push_notification_job_to_queue(): void
    {
        Queue::fake([SendWebPushNotificationJob::class]);

        $orderService = app(OrderService::class);
        $order = $orderService->createForTeam($this->team, $this->employee, [
            'customer_name' => 'Alice Queue',
            'product_id' => $this->product->id,
            'closing_value' => 500.00,
        ]);

        $this->assertNotNull($order);

        // Verify that SendWebPushNotificationJob was pushed to the queue
        Queue::assertPushed(SendWebPushNotificationJob::class, function ($job) use ($order) {
            return $job->title === 'New Order Attributed'
                && in_array('employee-'.$this->employee->id, $job->interests, true)
                && str_contains($job->body, $order->order_number);
        });

        // Also pushed for admin
        Queue::assertPushed(SendWebPushNotificationJob::class, function ($job) use ($order) {
            return $job->title === 'New LeadX Order'
                && in_array('admin', $job->interests, true)
                && str_contains($job->body, $order->order_number);
        });
    }

    /**
     * Test 2: In-app database notifications are created synchronously even when push is queued.
     */
    public function test_in_app_database_notifications_persist_synchronously(): void
    {
        Queue::fake([SendWebPushNotificationJob::class]);

        $orderService = app(OrderService::class);
        $order = $orderService->createForTeam($this->team, $this->employee, [
            'customer_name' => 'Bob InApp',
            'product_id' => $this->product->id,
            'closing_value' => 500.00,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->employee->id,
        ]);

        $notification = $this->employee->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('New Order Attributed', $notification->data['title']);
        $this->assertStringContainsString($order->order_number, $notification->data['message']);
    }

    /**
     * Test 3: Rolled-back database transactions DO NOT dispatch push notification jobs.
     */
    public function test_rolled_back_transactions_do_not_dispatch_push_notifications(): void
    {
        // 1. Verify job contract and configuration guarantees afterCommit semantics
        $sampleJob = new SendWebPushNotificationJob('Test', 'Body', ['admin']);
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueueAfterCommit::class, $sampleJob);
        $this->assertTrue($sampleJob->afterCommit);

        // 2. Track outgoing HTTP requests to verify no push notifications are dispatched on rollback
        $sentHttpCalls = 0;
        Http::fake([
            '*.pushnotifications.pusher.com/*' => function () use (&$sentHttpCalls) {
                $sentHttpCalls++;
                return Http::response(['publishId' => 'pub-rollback-test'], 200);
            },
        ]);

        try {
            DB::transaction(function () {
                $order = SalesOrder::create([
                    'order_number' => 'ROLLBACK-2026-000001',
                    'team_id' => $this->team->id,
                    'team_code_snapshot' => $this->team->team_code,
                    'assigned_employee_id' => $this->employee->id,
                    'customer_name' => 'Rollback Customer',
                    'product_value' => 100,
                    'closing_value' => 100,
                    'discount_amount' => 0,
                    'duration' => '1y',
                    'closing_value_status' => 'Accepted',
                    'order_status' => 'pending',
                ]);

                app(NotificationHubService::class)->notifyOrderCreated($order);

                // Simulate an unexpected database error triggering transaction rollback
                throw new \RuntimeException('Database rollback simulation');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Database rollback simulation', $e->getMessage());
        }

        // With afterCommit = true and ShouldQueueAfterCommit, no push notification was executed
        $this->assertSame(0, $sentHttpCalls, 'Push notification was dispatched despite transaction rollback!');
        $this->assertDatabaseMissing('sales_orders', [
            'order_number' => 'ROLLBACK-2026-000001',
        ]);
    }

    /**
     * Test 4: SendWebPushNotificationJob executes successfully and communicates with BeamsService.
     */
    public function test_push_notification_job_executes_successfully(): void
    {
        Http::fake([
            '*.pushnotifications.pusher.com/*' => Http::response(['publishId' => 'pub-test-job-999'], 200),
        ]);

        $job = new SendWebPushNotificationJob(
            title: 'Test Notification',
            body: 'Hello world',
            interests: ['employee-1'],
            deduplicationKey: 'test_job_success_' . uniqid()
        );

        $job->handle(app(BeamsService::class));

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/publishes')
                && $request['interests'] === ['employee-1']
                && $request['web']['notification']['title'] === 'Test Notification';
        });
    }

    /**
     * Test 5: Accidental duplicate notifications are suppressed within deduplication window.
     */
    public function test_duplicate_notifications_are_prevented_by_dedup_key(): void
    {
        $callCount = 0;
        Http::fake([
            '*.pushnotifications.pusher.com/*' => function () use (&$callCount) {
                $callCount++;
                return Http::response(['publishId' => 'pub-call-' . $callCount], 200);
            },
        ]);

        $dedupKey = 'unique_dedup_test_' . uniqid();

        $job1 = new SendWebPushNotificationJob('Order Shipped', 'Your order is on the way', ['admin'], $dedupKey);
        $job1->handle(app(BeamsService::class));

        $this->assertSame(1, $callCount, 'First job must execute HTTP request');

        // Second identical job should be deduplicated
        $job2 = new SendWebPushNotificationJob('Order Shipped', 'Your order is on the way', ['admin'], $dedupKey);
        $job2->handle(app(BeamsService::class));

        $this->assertSame(1, $callCount, 'Second job with same dedup key must be skipped without hitting API');
    }

    /**
     * Test 6: Transient 5xx server error from Pusher triggers job retry.
     */
    public function test_transient_server_error_triggers_retry_exception(): void
    {
        Http::fake([
            '*.pushnotifications.pusher.com/*' => Http::response(['error' => 'Gateway Timeout'], 504),
        ]);

        $job = new SendWebPushNotificationJob('Test Alert', 'Will fail', ['admin'], 'retry_test_' . uniqid());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pusher Beams gateway error: HTTP 504');

        $job->handle(app(BeamsService::class));
    }

    /**
     * Test 7: Permanent 4xx client error (e.g. invalid interest) is logged without re-throwing.
     */
    public function test_permanent_client_error_does_not_retry_indefinitely(): void
    {
        Http::fake([
            '*.pushnotifications.pusher.com/*' => Http::response(['error' => 'Invalid interest name'], 422),
        ]);

        $job = new SendWebPushNotificationJob('Bad Alert', 'Will be rejected', ['invalid interest'], 'client_err_' . uniqid());

        // Should NOT throw an exception so the queue worker does not endlessly retry client errors
        $job->handle(app(BeamsService::class));

        $this->assertTrue(true, '4xx client error was handled safely without throwing');
    }

    /**
     * Test 8: Permanent failure logs error safely without exposing secret keys.
     */
    public function test_failed_method_sanitizes_secrets_from_log(): void
    {
        // Synthetic key/instance only — never bake real .env credentials
        // into committed test fixtures.
        $serverKey = 'SAMPLE_SECRET_KEY_123';
        $instanceId = '11111111-2222-3333-4444-555555555555';
        config([
            'services.pusher.server_key' => $serverKey,
            'services.pusher.instance_id' => $instanceId,
        ]);

        $job = new SendWebPushNotificationJob('Secret Leak Test', 'Sensitive body', ['admin']);

        // Test the sanitization method directly
        $reflection = new \ReflectionClass($job);
        $sanitizeMethod = $reflection->getMethod('sanitizeError');
        $sanitizeMethod->setAccessible(true);

        $errorMessage = "Error connecting with key: {$serverKey} and instance {$instanceId}";
        $sanitized = $sanitizeMethod->invoke($job, $errorMessage);

        $this->assertStringNotContainsString($serverKey, $sanitized);
        $this->assertStringNotContainsString($instanceId, $sanitized);
        $this->assertStringContainsString('***REDACTED***', $sanitized);
        $this->assertStringContainsString('***REDACTED_INSTANCE***', $sanitized);
    }
}
