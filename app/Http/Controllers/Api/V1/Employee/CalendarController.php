<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeCalendarEventResource;
use App\Http\Resources\EmployeeGoogleConnectionResource;
use App\Models\User;
use App\Services\Google\EmployeeCalendarEventService;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Employee calendar — mirrors the employee web panel's "My Google Calendar"
 * page as a REST API, plus the personal-event CRUD and sync actions the web
 * panel drives through the same Google integration.
 *
 * The OAuth connection itself is deliberately NOT duplicated here: it goes
 * through the existing {@see GoogleOAuthService} (used by the web callback
 * and by GET /employee/google/calendar/* in the API), and event writes go
 * through the shared calendar HTTP conventions (token refresh, x-goog-api-key,
 * bounded timeouts). One integration, one OAuth flow.
 */
class CalendarController extends Controller
{
    use EmployeeScoped;

    public function __construct(
        protected EmployeeCalendarEventService $calendar,
        protected GoogleOAuthService $oauth,
    ) {
    }

    /**
     * GET /api/v1/employee/calendar
     *
     * Connection status + the employee's own calendar events (paginated
     * list of local rows; remote reads are not polled on request to keep
     * the payload and token cost bounded).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $connection = $employee->googleCalendarConnection()->first();

        $events = $this->calendar->listFor($employee);

        return response()->json([
            'connection' => [
                'connected' => $connection !== null && $connection->status === 'connected',
                'status' => $connection?->status,
                // The resource does not guard against a null resource, so it
                // is only embedded when a connection row exists.
                'data' => $connection ? new EmployeeGoogleConnectionResource($connection) : null,
            ],
            'data' => EmployeeCalendarEventResource::collection($events),
        ]);
    }

    /**
     * POST /api/v1/employee/calendar/events
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_at' => ['required', 'date'],
            'end_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_at'],
        ]);

        $result = $this->calendar->create($employee, $data);

        // 201 even when the remote write is still pending — the local event
        // row was created; `meta.synced` tells the client whether Google
        // confirmed it (a later /calendar/sync retries any that did not).
        return response()->json([
            'meta' => ['synced' => $result['created'], 'error' => $result['error']],
            'data' => new EmployeeCalendarEventResource($result['event']),
        ], 201);
    }

    /**
     * PUT /api/v1/employee/calendar/events/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'summary' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'start_at' => ['sometimes', 'date'],
            'end_at' => ['sometimes', 'nullable', 'date'],
        ]);

        // Cross-check the times against the event's current values, not just
        // what was sent in this payload.
        $event = \App\Models\EmployeeCalendarEvent::forEmployee($employee->id)->whereKey($id)->firstOrFail();
        $start = $data['start_at'] ?? $event->start_at;
        $end = array_key_exists('end_at', $data) ? $data['end_at'] : $event->end_at;

        if ($end && $start && strtotime($end) < strtotime($start)) {
            throw ValidationException::withMessages(['end_at' => 'End time cannot be before the start time.']);
        }

        $result = $this->calendar->update($employee, $id, $data);

        return response()->json([
            'meta' => ['synced' => $result['updated'], 'error' => $result['error']],
            'data' => new EmployeeCalendarEventResource($result['event']),
        ]);
    }

    /**
     * DELETE /api/v1/employee/calendar/events/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $result = $this->calendar->delete($employee, $id);

        return response()->json([
            'meta' => ['deleted' => $result['deleted'], 'error' => $result['error']],
            'data' => new EmployeeCalendarEventResource($result['event']),
        ]);
    }

    /**
     * POST /api/v1/employee/calendar/sync
     *
     * Retries this employee's pending / errored calendar writes (their own
     * personal events plus subscription expiry events), through the same
     * shared Google integration. Idempotent — a retried sync never creates
     * a duplicate remote event.
     */
    public function sync(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        return response()->json(['meta' => $this->calendar->sync($employee)]);
    }

    /**
     * Start the Google Calendar OAuth flow for the caller — reuses the
     * existing connect endpoint's service so the browser flow is identical
     * to the web panel (docs: google-calendar-setup.md).
     *
     * Exposed as an employee-scoped variant of the shared connect endpoint so
     * a Flutter app can kick off the OAuth without a web session.
     */
    public function connect(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        if (blank(config('google.client_id')) || blank(config('google.client_secret')) || blank(config('google.redirect_uri'))) {
            abort(503, 'Google Calendar integration is not configured on this server.');
        }

        $payload = $this->oauth->authorizationUrl($employee);

        return response()->json([
            'url' => $payload['url'],
            'state' => $payload['state'],
            'redirect_uri' => config('google.redirect_uri'),
        ]);
    }
}
