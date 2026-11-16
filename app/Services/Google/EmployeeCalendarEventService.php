<?php

namespace App\Services\Google;

use App\Models\EmployeeCalendarEvent;
use App\Models\EmployeeGoogleConnection;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Personal calendar events for the employee panel API.
 *
 * This service reuses the existing Google Calendar integration — the same
 * EmployeeGoogleConnection rows, the same token refresh logic in
 * GoogleOAuthService, and the same HTTP client conventions as
 * GoogleCalendarService. There is no duplicate OAuth or calendar client.
 *
 * Each event has a local row (strict ownership by employee_id) that carries
 * the remote Google event id, so updates/deletes address a stable local id
 * and retried writes never create a duplicate remote event.
 */
class EmployeeCalendarEventService
{
    public function __construct(protected GoogleOAuthService $oauth, protected SubscriptionCalendarService $subscriptions)
    {
    }

    /**
     * A usable connection + token pair for the employee.
     *
     * @return array{connection: EmployeeGoogleConnection, token: string, calendarId: string}|null
     */
    protected function activeConnection(User $employee): ?array
    {
        /** @var EmployeeGoogleConnection|null $connection */
        $connection = $employee->googleCalendarConnection()->first();

        if (! $connection || $connection->status !== 'connected') {
            return null;
        }

        $token = $this->oauth->currentAccessToken($connection);
        if ($token === null) {
            return null;
        }

        $calendarId = $connection->calendar_id ?? 'primary';

        return ['connection' => $connection, 'token' => $token, 'calendarId' => $calendarId];
    }

    protected function endpoint(string $path): string
    {
        return rtrim(config('google.calendar_api_base'), '/').'/'.$path;
    }

    /**
     * List the employee's events (local rows, newest first, bounded).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, EmployeeCalendarEvent>
     */
    public function listFor(User $employee): \Illuminate\Database\Eloquent\Collection
    {
        return EmployeeCalendarEvent::forEmployee($employee->id)
            ->orderByDesc('start_at')
            ->take(200)
            ->get();
    }

    /**
     * Create a personal event. Persists a local row first; then attempts the
     * remote write. A failed remote write marks the row `error` (never rolls
     * back the local record); a later sync retries it.
     *
     * @return array{event: EmployeeCalendarEvent, created: bool, error: ?string}
     */
    public function create(User $employee, array $data): array
    {
        $event = EmployeeCalendarEvent::create([
            'employee_id' => $employee->id,
            'summary' => $data['summary'],
            'description' => $data['description'] ?? null,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'] ?? null,
            'status' => 'synced',
        ]);

        $ctx = $this->activeConnection($employee);

        if (! $ctx) {
            $event->update([
                'status' => 'error',
                'last_message' => 'No active Google Calendar connection.',
            ]);

            return ['event' => $event->fresh(), 'created' => false, 'error' => 'not_connected'];
        }

        $payload = $this->eventPayload($event, $data);

        $response = $this->client($ctx['token'])->post(
            $this->endpoint("calendars/{$ctx['calendarId']}/events"),
            $payload
        );

        if ($response->failed()) {
            $event->update([
                'status' => 'error',
                'last_message' => "Google event creation failed (HTTP {$response->status()}).",
            ]);

            report("EmployeeCalendarEventService: create failed for event {$event->id}: HTTP {$response->status()}");

            return ['event' => $event->fresh(), 'created' => false, 'error' => 'google_error'];
        }

        $body = $response->json();
        $eventId = $body['id'] ?? null;

        $event->forceFill([
            'google_event_id' => $eventId,
            'status' => 'synced',
            'last_synced_at' => now(),
            'last_message' => 'Event created in Google Calendar.',
        ])->save();

        $ctx['connection']->forceFill(['last_synced_at' => now()])->save();

        return ['event' => $event->fresh(), 'created' => true, 'error' => null];
    }

    /**
     * Update a personal event (employee-owned only). Persists local field
     * changes first; then applies them remotely — creating the remote event
     * when one does not exist yet, or patching the existing one (idempotent:
     * the remote id is stored on the local row).
     *
     * @return array{event: EmployeeCalendarEvent, updated: bool, error: ?string}
     */
    public function update(User $employee, int $id, array $data): array
    {
        $event = EmployeeCalendarEvent::forEmployee($employee->id)->whereKey($id)->firstOrFail();

        $ctx = $this->activeConnection($employee);

        if (! $ctx) {
            $event->update(['status' => 'error', 'last_message' => 'No active Google Calendar connection.']);

            return ['event' => $event->fresh(), 'updated' => false, 'error' => 'not_connected'];
        }

        foreach (['summary', 'description', 'start_at', 'end_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $event->{$field} = $data[$field];
            }
        }

        $event->save();

        $payload = $this->eventPayload($event, []);

        if ($event->hasRemoteEvent()) {
            $response = $this->client($ctx['token'])->patch(
                $this->endpoint("calendars/{$ctx['calendarId']}/events/{$event->google_event_id}"),
                $payload
            );
        } else {
            // No remote event yet: create it and record the id (never
            // creates a second local row).
            $response = $this->client($ctx['token'])->post(
                $this->endpoint("calendars/{$ctx['calendarId']}/events"),
                $payload
            );
        }

        if ($response->failed()) {
            $event->forceFill([
                'status' => 'error',
                'last_message' => "Google event update failed (HTTP {$response->status()}).",
            ])->save();

            return ['event' => $event->fresh(), 'updated' => false, 'error' => 'google_error'];
        }

        $body = $response->json();

        $event->forceFill([
            'google_event_id' => $event->hasRemoteEvent() ? $event->google_event_id : ($body['id'] ?? null),
            'status' => 'synced',
            'last_synced_at' => now(),
            'last_message' => 'Event synced with Google Calendar.',
        ])->save();

        $ctx['connection']->forceFill(['last_synced_at' => now()])->save();

        return ['event' => $event->fresh(), 'updated' => true, 'error' => null];
    }

    /**
     * Delete a personal event (employee-owned only). Removes the remote event
     * when present (a 404 is treated as success for idempotency), then marks
     * the local row deleted.
     *
     * @return array{event: EmployeeCalendarEvent, deleted: bool, error: ?string}
     */
    public function delete(User $employee, int $id): array
    {
        $event = EmployeeCalendarEvent::forEmployee($employee->id)->whereKey($id)->firstOrFail();

        if ($event->hasRemoteEvent()) {
            $ctx = $this->activeConnection($employee);

            if ($ctx) {
                $response = $this->client($ctx['token'])->delete(
                    $this->endpoint("calendars/{$ctx['calendarId']}/events/{$event->google_event_id}")
                );

                if ($response->failed() && $response->status() !== 404) {
                    $event->forceFill(['status' => 'error', 'last_message' => 'Remote delete failed.'])->save();

                    return ['event' => $event->fresh(), 'deleted' => false, 'error' => 'google_error'];
                }
            }
        }

        $event->forceFill([
            'status' => 'deleted',
            'last_message' => 'Event deleted.',
            'last_synced_at' => now(),
        ])->save();

        return ['event' => $event->fresh(), 'deleted' => true, 'error' => null];
    }

    /**
     * Sync: retry every calendar event that belongs to this employee and is
     * not yet synced — their own personal events marked `error` without a
     * remote id, plus any pending subscription expiry events. Idempotent:
     * every remote write is keyed on a stored google_event_id, so a retried
     * sync never creates a duplicate event.
     *
     * @return array{events_synced: int, retried: int, failed: int, connected: bool}
     */
    public function sync(User $employee): array
    {
        $ctx = $this->activeConnection($employee);
        $synced = 0;
        $failed = 0;

        if ($ctx) {
            // Retry the employee's personal events that failed their first
            // remote write (no google_event_id yet).
            EmployeeCalendarEvent::forEmployee($employee->id)
                ->where('status', 'error')
                ->whereNull('google_event_id')
                ->get()
                ->each(function (EmployeeCalendarEvent $event) use ($ctx, &$synced, &$failed): void {
                    $payload = $this->eventPayload($event, []);
                    $response = $this->client($ctx['token'])->post(
                        $this->endpoint("calendars/{$ctx['calendarId']}/events"),
                        $payload
                    );

                    if ($response->successful()) {
                        $event->forceFill([
                            'google_event_id' => $response->json('id'),
                            'status' => 'synced',
                            'last_synced_at' => now(),
                            'last_message' => 'Event created on sync retry.',
                        ])->save();
                        $synced++;
                    } else {
                        $event->forceFill(['last_message' => "Google event creation failed (HTTP {$response->status()})."])->save();
                        $failed++;
                    }
                });
        }

        return [
            'events_synced' => $synced,
            'retried' => $this->retryPendingFor($employee),
            'failed' => $failed,
            'connected' => $ctx !== null,
        ];
    }

    /**
     * Retry pending subscription calendar events assigned to the employee,
     * through the shared subscription calendar service.
     */
    protected function retryPendingFor(User $employee): int
    {
        $count = 0;

        Subscription::whereIn('calendar_sync_status', ['pending', 'not_connected', 'sync_error'])
            ->whereNull('google_event_id')
            ->whereHas('order', fn ($q) => $q->where('assigned_employee_id', $employee->id))
            ->get()
            ->each(function (Subscription $subscription) use (&$count): void {
                $result = $this->subscriptions->syncCreate($subscription);
                if ($result['created']) {
                    $count++;
                }
            });

        return $count;
    }

    protected function eventPayload(EmployeeCalendarEvent $event, array $data): array
    {
        $start = $event->start_at ?? now();
        $end = $event->end_at ?? $start->copy()->addMinutes((int) config('google.calendar.default_event_minutes', 30));

        return [
            'summary' => $event->summary,
            'description' => $event->description,
            'start' => ['dateTime' => $start->toIso8601String()],
            'end' => ['dateTime' => $end->toIso8601String()],
        ];
    }

    protected function client(?string $token): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::acceptJson()->timeout(10);

        if ($token !== null) {
            $request = $request->withToken($token);
        }

        $apiKey = config('google.api_key');
        if (! empty($apiKey)) {
            $request = $request->withHeader('X-Goog-Api-Key', $apiKey);
        }

        return $request;
    }
}
