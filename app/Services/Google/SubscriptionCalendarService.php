<?php

namespace App\Services\Google;

use App\Models\EmployeeGoogleConnection;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;

/**
 * Google Calendar sync for subscription expiry reminders.
 *
 * When a subscription activates, an expiry-reminder event is created on the
 * *assigned employee's* connected Google account (the same per-employee OAuth
 * authorization rules as the follow-up calendar — the team's shared login is
 * never used). The service is idempotent: every event is keyed on the
 * subscription's stored `google_event_id`, so a retried sync never creates a
 * second event.
 *
 * When no employee has a connected calendar yet, the subscription is marked
 * `pending`/`not_connected` rather than erroring, and {@see retryPending()}
 * (driven by the scheduler) picks those up later — the requirement that a
 * missing connection records a pending sync and allows retry.
 *
 * The key is never written to the calendar or to logs; only order/customer
 * identifiers and dates go into the event body.
 */
class SubscriptionCalendarService
{
    public function __construct(protected GoogleOAuthService $oauth)
    {
    }

    /**
     * Create (or, on retry, reconcile) the expiry-reminder event for a
     * subscription.
     *
     * @return array{created: bool, event_id: ?string, error: ?string}
     */
    public function syncCreate(Subscription $subscription): array
    {
        // Idempotency: a subscription that already has a remote event is
        // never re-created. Reconcile instead.
        if ($subscription->hasCalendarEvent()) {
            $this->mark($subscription, 'synced', 'Event already exists; reconciled (no duplicate created).');

            return ['created' => false, 'event_id' => $subscription->google_event_id, 'error' => null];
        }

        $employee = $this->resolveEmployee($subscription);

        if ($employee === null) {
            // No assigned employee at all: there is nothing to attach to, so
            // record a pending sync for a later retry.
            $this->mark($subscription, 'pending', 'No assigned employee; expiry event pending a retry.');

            return ['created' => false, 'event_id' => null, 'error' => 'no_employee'];
        }

        $connection = $employee->googleCalendarConnection()->first();

        if (! $connection || $connection->status !== 'connected') {
            $this->mark($subscription, 'not_connected', 'Assigned employee has no active Google Calendar connection; event pending a retry.');

            return ['created' => false, 'event_id' => null, 'error' => 'not_connected'];
        }

        $calendarId = $this->primaryCalendarId($connection);

        if ($calendarId === null) {
            $this->mark($subscription, 'sync_error', 'Could not resolve the employee\'s primary calendar.');

            return ['created' => false, 'event_id' => null, 'error' => 'calendar_unresolvable'];
        }

        $token = $this->oauth->currentAccessToken($connection);

        if ($token === null) {
            $this->mark($subscription, 'sync_error', 'No usable Google access token (refresh failed or revoked); event pending a retry.');

            return ['created' => false, 'event_id' => null, 'error' => 'auth_unavailable'];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->endpoint("calendars/{$calendarId}/events"), $this->eventPayload($subscription));

        if ($response->failed()) {
            $this->mark($subscription, 'sync_error', 'Google event creation failed.');
            // The body may echo request details; log a stable marker only.
            report("SubscriptionCalendarService: create failed for subscription {$subscription->id} (HTTP {$response->status()}).");

            return ['created' => false, 'event_id' => null, 'error' => 'google_error'];
        }

        $eventId = $response->json('id');

        $subscription->forceFill([
            'google_event_id' => $eventId,
            'calendar_sync_status' => 'synced',
            'calendar_sync_message' => 'Expiry-reminder event created.',
            'calendar_synced_at' => now(),
        ])->save();
        $connection->forceFill(['last_synced_at' => now()])->save();

        return ['created' => true, 'event_id' => $eventId, 'error' => null];
    }

    /**
     * Move an existing event to the new expiry time (renewal / date change).
     *
     * @return array{updated: bool, event_id: ?string, error: ?string}
     */
    public function syncUpdate(Subscription $subscription): array
    {
        if (! $subscription->hasCalendarEvent()) {
            // Nothing remote to move yet — create it on first sync.
            return $this->syncCreate($subscription);
        }

        $employee = $this->resolveEmployee($subscription);
        $connection = $employee?->googleCalendarConnection()->first();

        if (! $connection || $connection->status !== 'connected') {
            $this->mark($subscription, 'sync_error', 'Cannot update: assigned employee\'s Google connection is inactive.');

            return ['updated' => false, 'event_id' => $subscription->google_event_id, 'error' => 'not_connected'];
        }

        $calendarId = $connection->calendar_id ?? 'primary';
        $token = $this->oauth->currentAccessToken($connection);

        if ($token === null) {
            $this->mark($subscription, 'sync_error', 'No usable Google access token.');

            return ['updated' => false, 'event_id' => $subscription->google_event_id, 'error' => 'auth_unavailable'];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->put($this->endpoint("calendars/{$calendarId}/events/{$subscription->google_event_id}"), $this->eventPayload($subscription));

        if ($response->failed()) {
            $this->mark($subscription, 'sync_error', 'Google event update failed.');
            report("SubscriptionCalendarService: update failed for subscription {$subscription->id} (HTTP {$response->status()}).");

            return ['updated' => false, 'event_id' => $subscription->google_event_id, 'error' => 'google_error'];
        }

        $this->mark($subscription, 'synced', 'Expiry-reminder event rescheduled in Google.');

        return ['updated' => true, 'event_id' => $subscription->google_event_id, 'error' => null];
    }

    /**
     * Delete the remote event when a subscription is cancelled. The
     * `google_event_id` is cleared so a later re-activation re-creates a
     * fresh event.
     *
     * @return array{cancelled: bool, error: ?string}
     */
    public function syncCancel(Subscription $subscription): array
    {
        if (! $subscription->hasCalendarEvent()) {
            $this->mark($subscription, 'synced', 'Cancelled; no Google event to delete.');

            return ['cancelled' => true, 'error' => null];
        }

        $employee = $this->resolveEmployee($subscription);
        $connection = $employee?->googleCalendarConnection()->first();
        $calendarId = $connection?->calendar_id ?? 'primary';

        if ($connection && $connection->status === 'connected') {
            $token = $this->oauth->currentAccessToken($connection);

            if ($token !== null) {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->delete($this->endpoint("calendars/{$calendarId}/events/{$subscription->google_event_id}"));

                // 404 = already gone out-of-band; treat as success.
                if (! ($response->successful() || $response->status() === 404)) {
                    $this->mark($subscription, 'sync_error', 'Google event cancel failed; remote event may still exist.');

                    return ['cancelled' => false, 'error' => 'google_error'];
                }
            }
        }

        $subscription->forceFill([
            'google_event_id' => null,
            'calendar_sync_status' => 'synced',
            'calendar_sync_message' => 'Google event cancelled.',
            'calendar_synced_at' => now(),
        ])->save();

        return ['cancelled' => true, 'error' => null];
    }

    /**
     * Re-attempt every subscription whose calendar sync is pending or errored
     * and that still has a valid (active or soon-expiring) lifecycle. Driven
     * by the scheduler so it does not depend on a browser staying open.
     */
    public function retryPending(): int
    {
        $count = 0;

        Subscription::whereIn('calendar_sync_status', ['pending', 'not_connected', 'sync_error'])
            ->whereNotNull('expires_at')
            ->whereIn('status', ['active', 'expired'])
            ->whereNull('google_event_id') // only rows without a live event
            ->chunk(100, function ($batch) use (&$count): void {
                foreach ($batch as $subscription) {
                    if ($subscription->hasCalendarEvent()) {
                        continue; // already has one — skip.
                    }

                    $result = $this->syncCreate($subscription);

                    if ($result['created']) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * The employee whose connected calendar should own the event: the order's
     * assigned employee. Null when the order has no assigned employee.
     */
    protected function resolveEmployee(Subscription $subscription): ?\App\Models\User
    {
        $order = $subscription->order;

        if ($order === null) {
            return null;
        }

        return $order->assignedEmployee;
    }

    /**
     * Build the expiry-reminder event payload. Only identifiers and dates —
     * never the customer's API key or any token.
     */
    protected function eventPayload(Subscription $subscription): array
    {
        $order = $subscription->order;
        $customer = $order?->customer_name ?? 'Unknown customer';
        $orderNumber = $order?->order_number ?? ('#'.$subscription->id);

        $start = ($subscription->expires_at ?? now())->copy()->startOfDay();
        $minutes = (int) config('google.calendar.default_event_minutes', 30);
        $end = $start->copy()->addMinutes($minutes);

        $lines = [
            'LeadX Subscription Expiry Reminder',
            'Customer: '.$customer,
            'Order ID: '.$orderNumber,
            'Product: '.($order?->product_name_snapshot ?? $order?->product_name ?? '—'),
            'Activation Date: '.($subscription->started_at?->toDateString() ?? '—'),
            'Expiry Date: '.($subscription->expires_at?->toDateString() ?? '—'),
            'Status: '.ucfirst(str_replace('_', ' ', (string) $subscription->status)),
            'Subscription ID: #'.$subscription->id,
        ];

        return [
            'summary' => 'LeadX Expiry: '.$customer.' ('.$orderNumber.')',
            'description' => implode("\n", $lines),
            'start' => ['dateTime' => $start->toIso8601String()],
            'end' => ['dateTime' => $end->toIso8601String()],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    // Popup one day before the expiry — mirrors the team
                    // in-app reminder timing.
                    ['method' => 'popup', 'minutes' => (int) config('leadx.google.expiry_reminder_lead_minutes', 1440)],
                ],
            ],
        ];
    }

    /**
     * Resolve and cache the employee's primary calendar id.
     */
    protected function primaryCalendarId(EmployeeGoogleConnection $connection): ?string
    {
        if (filled($connection->calendar_id)) {
            return $connection->calendar_id;
        }

        $token = $this->oauth->currentAccessToken($connection);

        if ($token === null) {
            return null;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->endpoint('calendars/primary'));

        if ($response->failed()) {
            return null;
        }

        $calendarId = $response->json('id');

        if ($calendarId) {
            $connection->forceFill(['calendar_id' => $calendarId, 'last_synced_at' => now()])->save();
        }

        return $calendarId;
    }

    protected function mark(Subscription $subscription, string $status, string $message): void
    {
        $subscription->forceFill([
            'calendar_sync_status' => $status,
            'calendar_sync_message' => $message,
            'calendar_synced_at' => now(),
        ])->save();
    }

    protected function endpoint(string $path): string
    {
        return rtrim(config('google.calendar_api_base'), '/').'/'.$path;
    }
}
