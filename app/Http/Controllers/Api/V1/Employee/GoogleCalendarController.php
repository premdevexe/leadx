<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeGoogleConnectionResource;
use App\Models\Team;
use App\Models\User;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Google Calendar OAuth endpoints for the employee's OWN account
 * (Phase 08, Module 2).
 *
 * These live under the employee guard on purpose. The module rule is that a
 * team's shared login must never be used to connect a personal Google
 * calendar — so there is deliberately no team/admin variant of these routes,
 * and the callback resolves the employee from the signed-in user only.
 */
class GoogleCalendarController extends Controller
{
    public function __construct(protected GoogleOAuthService $oauth)
    {
    }

    /**
     * GET /employee/google/calendar/connect
     * Returns the URL to send the employee's browser to, plus the state to
     * expect back in the callback.
     */
    public function connect(Request $request): JsonResponse
    {
        $employee = $this->requireEmployee($request);
        $this->ensureGoogleConfigured();

        $payload = $this->oauth->authorizationUrl($employee);

        return response()->json([
            'url' => $payload['url'],
            'state' => $payload['state'],
            'redirect_uri' => config('google.redirect_uri'),
        ]);
    }

    /**
     * GET|POST /google/calendar/callback
     *
     * The browser redirect from Google. It is a top-level navigation with NO
     * Bearer token, so identity is established by the signed single-use
     * `state` (see GoogleOAuthService::resolveEmployeeFromState). This is
     * what makes it structurally impossible for a team's shared login to
     * connect a personal Google calendar.
     */
    public function callback(Request $request): JsonResponse
    {
        $error = $request->input('error');
        // Google sends `error` (e.g. access_denied) instead of a code.
        if ($error !== null) {
            return $this->callbackFailure('Google returned an error: '.$error);
        }

        $this->ensureGoogleConfigured();

        // The callback may arrive as a query string (browser redirect) or a
        // form body; accept both.
        $code = $request->input('code');
        $state = $request->input('state');

        $result = $this->oauth->completeFromCallback($code, $state, $error);

        // No token is returned here (Module 2: never expose credentials).
        return response()->json([
            'message' => 'Google account connected.',
            'employee_id' => $result['employee']->id,
            'data' => [
                'connection' => new EmployeeGoogleConnectionResource($result['connection']),
            ],
        ]);
    }

    /**
     * GET /employee/google/calendar
     * Current connection status. No tokens are included.
     */
    public function status(Request $request): JsonResponse
    {
        $employee = $this->requireEmployee($request);

        $connection = $employee->googleCalendarConnection()->first();

        return response()->json([
            'data' => [
                'connected' => $connection !== null && $connection->status === 'connected',
                'connection' => new EmployeeGoogleConnectionResource($connection),
            ],
        ]);
    }

    /**
     * POST /employee/google/calendar/disconnect
     * Revoke access and mark the connection disconnected.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $employee = $this->requireEmployee($request);

        $connection = $employee->googleCalendarConnection()->first();

        if ($connection !== null && $connection->status === 'connected') {
            $this->oauth->disconnect($connection);
        }

        return response()->json([
            'message' => 'Google account disconnected.',
            'data' => [
                'connection' => new EmployeeGoogleConnectionResource($employee->googleCalendarConnection()->first()),
            ],
        ]);
    }

    /**
     * The OAuth flow is personal to the employee. A team token (or any
     * non-employee token) is rejected here.
     */
    protected function requireEmployee(Request $request): User
    {
        $actor = $request->user('sanctum');

        // A team (shared) login must never be used to connect a personal
        // Google Calendar. The token must belong to an individual employee.
        if (! $actor instanceof User) {
            abort(403, 'Only an individual employee can connect their own Google Calendar. A team login cannot be used.');
        }

        // The account must be an employee role, not a team-scoped or admin
        // login that happens to sit in the users table.
        if (($actor->role ?? 'employee') !== 'employee') {
            abort(403, 'A team shared login cannot connect a personal Google Calendar.');
        }

        return $actor;
    }

    protected function ensureGoogleConfigured(): void
    {
        if (blank(config('google.client_id')) || blank(config('google.client_secret')) || blank(config('google.redirect_uri'))) {
            abort(503, 'Google Calendar integration is not configured on this server.');
        }
    }

    protected function callbackFailure(string $message): JsonResponse
    {
        // Without a valid employee token we can't tie the failure to a
        // connection; return a JSON error so an SPA can surface it.
        return response()->json(['message' => $message], 400);
    }
}
