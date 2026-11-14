<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\PushNotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationResendTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    public function test_a_failed_notification_can_be_retried_from_history(): void
    {
        $log = PushNotificationLog::create([
            'admin_id' => $this->admin->id,
            'title' => 'Mine',
            'body' => 'Old body',
            'interests' => 'Divi-Chellow',
            'status' => 'failed',
            'error' => 'HTTP status 422',
        ]);

        Http::fake([
            '*pushnotifications.pusher.com/*' => Http::response(['publishId' => 'retry-123'], 200),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.notifications.resend', $log));

        $response->assertRedirect(route('admin.notifications'));

        $fresh = PushNotificationLog::where('publish_id', 'retry-123')->first();
        $this->assertNotNull($fresh);
        $this->assertSame('sent', $fresh->status);
        $this->assertSame('Mine', $fresh->title);
        $this->assertSame('Old body', $fresh->body);
        $this->assertSame('Divi-Chellow', $fresh->interests);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/publish_api/v1/instances/');
        });
    }

    public function test_a_failed_notification_with_invalid_interests_can_be_retried_with_corrected_interests(): void
    {
        $log = PushNotificationLog::create([
            'admin_id' => $this->admin->id,
            'title' => 'Mine',
            'body' => 'Old body',
            'interests' => 'Divi Chellow',
            'status' => 'failed',
            'error' => 'HTTP status 422',
        ]);

        Http::fake([
            '*pushnotifications.pusher.com/*' => Http::response(['publishId' => 'fixed-456'], 200),
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.notifications.resend', $log), ['interests' => 'Divi-Chellow']);

        $response->assertRedirect(route('admin.notifications'));

        $fresh = PushNotificationLog::where('publish_id', 'fixed-456')->first();
        $this->assertNotNull($fresh);
        $this->assertSame('sent', $fresh->status);
        $this->assertSame('Divi-Chellow', $fresh->interests);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/publish_api/v1/instances/');
        });
    }

    public function test_a_retry_with_invalid_corrected_interests_is_rejected_with_per_row_feedback(): void
    {
        $log = PushNotificationLog::create([
            'admin_id' => $this->admin->id,
            'title' => 'Mine',
            'body' => 'Old body',
            'interests' => 'Divi Chellow',
            'status' => 'failed',
            'error' => 'HTTP status 422',
        ]);

        Http::fake();

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.notifications.resend', $log), ['interests' => 'Bad Interest']);

        $response->assertRedirect();
        $response->assertSessionHas('retry_error.'.$log->id);
        $this->assertStringContainsString("'Bad Interest'", session('retry_error.'.$log->id));
        // The edited value is remembered so the admin can fix and resubmit.
        $this->assertSame('Bad Interest', session('retry_interests.'.$log->id));

        $this->assertSame(1, PushNotificationLog::count());
        Http::assertNothingSent();
    }

    public function test_a_plain_retry_uses_the_interests_recorded_on_the_failed_entry(): void
    {
        $log = PushNotificationLog::create([
            'admin_id' => $this->admin->id,
            'title' => 'Plain',
            'body' => 'Body',
            'interests' => 'hello, world', // recorded interests win when none submitted
            'status' => 'failed',
            'error' => 'HTTP status 500',
        ]);

        Http::fake([
            '*pushnotifications.pusher.com/*' => Http::response(['publishId' => 'plain-789'], 200),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.notifications.resend', $log))
            ->assertRedirect(route('admin.notifications'));

        $fresh = PushNotificationLog::where('publish_id', 'plain-789')->first();
        $this->assertNotNull($fresh);
        $this->assertSame('hello, world', $fresh->interests);
    }

    public function test_a_sent_notification_cannot_be_retried(): void
    {
        $log = PushNotificationLog::create([
            'admin_id' => $this->admin->id,
            'title' => 'Delivered',
            'body' => 'Body',
            'interests' => 'hello',
            'publish_id' => 'orig-1',
            'status' => 'sent',
        ]);

        Http::fake();

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.notifications.resend', $log));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Only failed notifications can be retried.');

        $this->assertSame(1, PushNotificationLog::count());
        Http::assertNothingSent();
    }

    public function test_a_failed_retry_is_logged_with_the_pusher_rejection_reason(): void
    {
        $log = PushNotificationLog::create([
            'admin_id' => $this->admin->id,
            'title' => 'Flaky',
            'body' => 'Body',
            'interests' => 'hello',
            'status' => 'failed',
            'error' => 'HTTP status 422',
        ]);

        Http::fake([
            '*pushnotifications.pusher.com/*' => Http::response(['message' => 'no VAPID keys configured'], 422),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.notifications.resend', $log))
            ->assertSessionHas('error');

        $this->assertStringContainsString('failed with HTTP status 422', session('error'));
        $this->assertStringContainsString('no VAPID keys configured', session('error'));

        $this->assertSame(2, PushNotificationLog::count());

        // Both log rows share a created_at second, so find the retry's row
        // by its error content rather than by timestamp.
        $retryLog = PushNotificationLog::all()
            ->filter(fn ($log) => str_contains((string) $log->error, 'no VAPID keys configured'))
            ->first();

        $this->assertNotNull($retryLog);
        $this->assertSame('failed', $retryLog->status);
        $this->assertNull($retryLog->publish_id);
    }

    public function test_index_filters_history_by_status(): void
    {
        PushNotificationLog::create([
            'admin_id' => $this->admin->id,
            'title' => 'Good',
            'body' => 'Sent',
            'interests' => 'hello',
            'publish_id' => 'p-1',
            'status' => 'sent',
        ]);
        PushNotificationLog::create([
            'admin_id' => $this->admin->id,
            'title' => 'Bad',
            'body' => 'Failed',
            'interests' => 'hello',
            'status' => 'failed',
            'error' => 'HTTP status 422',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.notifications', ['status' => 'failed']))
            ->assertOk()
            ->assertViewHas('status', 'failed');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.notifications'))
            ->assertOk()
            ->assertViewHas('status', null);
    }

    public function test_unauthenticated_users_cannot_resend(): void
    {
        $log = PushNotificationLog::create([
            'admin_id' => $this->admin->id,
            'title' => 'Secret',
            'body' => 'Body',
            'interests' => 'hello',
            'status' => 'failed',
        ]);

        $response = $this->post(route('admin.notifications.resend', $log));

        $this->assertNotEquals(200, $response->getStatusCode());
        Http::assertNothingSent();
    }
}
