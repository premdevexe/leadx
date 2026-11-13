<?php

namespace App\Services\Google;

use App\Models\EmployeeGoogleConnection;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Google Calendar event sync for follow-ups (Phase 08, Module 3).
 *
 * All writes go through the assigned employee's connected account. The
 * service is idempotent: it keys every event on the LeadX follow-up id,
 * checks the already-stored google_event_id first, and only creates/
 * updates/deletes against an existing event.
 *
 * The calendar used is the employee's "primary" calendar (resolved once and
 * cached on the connection).
 */
class GoogleCalendarService
{
    public function __construct(protected GoogleOAuthService $oauth)
    {
    }

    /**
     * Create (or, on retry, reconcile) a calendar event for a follow-up.
     *
     * @return array{created: bool, event_id: ?string, error: ?string}
     */
    public function syncCreate(FollowUp $followUp, ?User $employee): array
    {
        if ($employee === null) {
            $this->mark($followUp, 'not_connected', 'No employee account is available for the calendar event.');

            return ['created' => false, 'event_id' => null, 'error' => 'not_connected'];
        }

        $connection = $employee->googleCalendarConnection()->first();

        if (! $connection || $connection->status !== 'connected') {
            $this->mark($followUp, 'not_connected', 'Employee has no active Google Calendar connection.');

            return ['created' => false, 'event_id' => null, 'error' => 'not_connected'];
        }

        $calendarId = $this->primaryCalendarId($connection);

        if ($calendarId === null) {
            $this->mark($followUp, 'sync_error', 'Could not resolve the employee\'s primary calendar.');

            return ['created' => false, 'event_id' => null, 'error' => 'calendar_unresolvable'];
        }

        // Idempotency: a retried request for the same follow-up must not
        // create a second event.
        if ($followUp->hasCalendarEvent()) {
            $this->mark($followUp, 'synced', 'Event already exists; reconciled (no duplicate created).');

            return ['created' => false, 'event_id' => $followUp->google_event_id, 'error' => null];
        }

        $token = $this->oauth->currentAccessToken($connection);
        if ($token === null) {
            $this->mark($followUp, 'sync_error', 'No usable Google access token (refresh failed or revoked).');

            return ['created' => false, 'event_id' => null, 'error' => 'auth_unavailable'];
        }

        $event = $this->eventPayload($followUp);

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->endpoint("calendars/{$calendarId}/events"), $event);

        if ($response->failed()) {
            $this->mark($followUp, 'sync_error', 'Google event creation failed.');
            report("GoogleCalendarService: create failed for follow-up {$followUp->id}: ".$response->body());

            return ['created' => false, 'event_id' => null, 'error' => 'google_error'];
        }

        $body = $response->json();
        $eventId = $body['id'] ?? null;

        $followUp->forceFill([
            'google_event_id' => $eventId,
            'calendar_sync_status' => 'synced',
            'calendar_sync_message' => 'Event created.',
            'calendar_synced_at' => now(),
        ])->save();
        $connection->forceFill(['last_synced_at' => now()])->save();

        return ['created' => true, 'event_id' => $eventId, 'error' => null];
    }

    /**
     * Move an existing event to a new start time (reschedule).
     *
     * @return array{updated: bool, event_id: ?string, error: ?string}
     */
    public function syncUpdate(FollowUp $followUp, ?User $employee): array
    {
        if (! $followUp->hasCalendarEvent()) {
            // Nothing to update yet — fall through to create on first sync.
            return $this->syncCreate($followUp, $employee);
        }

        if ($employee === null) {
            // The remote event already exists; there is nothing to move and
            // no account to move it with. Leave the event as-is.
            $this->mark($followUp, 'synced', 'No employee account available; existing event left unchanged.');

            return ['updated' => false, 'event_id' => $followUp->google_event_id, 'error' => 'no_employee'];
        }

        $connection = $employee->googleCalendarConnection()->first();
        if (! $connection || $connection->status !== 'connected') {
            $this->mark($followUp, 'sync_error', 'Cannot update: employee Google connection is inactive.');

            return ['updated' => false, 'event_id' => $followUp->google_event_id, 'error' => 'not_connected'];
        }

        $calendarId = $connection->calendar_id ?? 'primary';
        $token = $this->oauth->currentAccessToken($connection);
        if ($token === null) {
            $this->mark($followUp, 'sync_error', 'No usable Google access token.');

            return ['updated' => false, 'event_id' => $followUp->google_event_id, 'error' => 'auth_unavailable'];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->put($this->endpoint("calendars/{$calendarId}/events/{$followUp->google_event_id}"), $this->eventPayload($followUp));

        if ($response->failed()) {
            $this->mark($followUp, 'sync_error', 'Google event update failed.');

            return ['updated' => false, 'event_id' => $followUp->google_event_id, 'error' => 'google_error'];
        }

        $this->mark($followUp, 'synced', 'Event rescheduled in Google.');

        return ['updated' => true, 'event_id' => $followUp->google_event_id, 'error' => null];
    }

    /**
     * Cancel a follow-up's event. We delete the remote event and keep the
     * LeadX history; the google_event_id is cleared so a later reschedule
     * can recreate cleanly.
     *
     * @return array{cancelled: bool, error: ?string}
     */
    public function syncCancel(FollowUp $followUp, ?User $employee): array
    {
        if (! $followUp->hasCalendarEvent()) {
            // No remote event to delete.
            $this->mark($followUp, 'synced', 'Cancelled; no Google event to delete.');

            return ['cancelled' => true, 'error' => null];
        }

        $connection = $employee?->googleCalendarConnection()->first();
        $calendarId = $connection?->calendar_id ?? 'primary';

        if ($connection && $connection->status === 'connected') {
            $token = $this->oauth->currentAccessToken($connection);
            if ($token !== null) {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->delete($this->endpoint("calendars/{$calendarId}/events/{$followUp->google_event_id}"));

                // A 404 means the event was already gone (deleted out of
                // band or revoked). Treat as success for idempotency.
                if ($response->successful() || $response->status() === 404) {
                    $this->mark($followUp, 'synced', 'Google event cancelled.');
                } else {
                    $this->mark($followUp, 'sync_error', 'Google event cancel failed; remote event may still exist.');

                    return ['cancelled' => false, 'error' => 'google_error'];
                }
            }
        }

        // Clear the reference so a future reschedule re-creates the event.
        $followUp->forceFill(['google_event_id' => null])->save();

        return ['cancelled' => true, 'error' => null];
    }

    /**
     * Build the event JSON for the Calendar API, including reminders.
     */
    protected function eventPayload(FollowUp $followUp): array
    {
        $start = $followUp->scheduled_at ?? now();
        $minutes = config('google.calendar.default_event_minutes', 30);
        $end = $followUp->next_follow_up_at ?? $start->copy()->addMinutes($minutes);

        $customer = $followUp->customer;
        $employee = $followUp->assignedEmployee;

        $descriptionLines = [];
        if ($customer) {
            $descriptionLines[] = 'Customer: '.$customer->name;
            if ($customer->phone) {
                $descriptionLines[] = 'Phone: '.$customer->phone;
            }
        }
        if ($followUp->description) {
            $descriptionLines[] = $followUp->description;
        }
        if ($employee) {
            $descriptionLines[] = 'Assigned employee: '.$employee->name;
        }
        if ($followUp->order) {
            $descriptionLines[] = 'LeadX Order: '.$followUp->order->order_number;
        }
        $descriptionLines[] = 'Follow-up ID: #'.$followUp->id;

        return [
            'summary' => $followUp->title,
            'description' => implode("\n", $descriptionLines),
            'start' => ['dateTime' => $start->toIso8601String()],
            'end' => ['dateTime' => $end->toIso8601String()],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => config('google.calendar.reminder_minutes', 30)],
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

    protected function mark(FollowUp $followUp, string $status, string $message): void
    {
        $followUp->forceFill([
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
