<?php

namespace App\Services\Google;

use App\Models\EmployeeGoogleConnection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Google Calendar OAuth 2.0 orchestration (Phase 08, Module 2).
 *
 * Each employee links their OWN Google account. Standard authorization-code
 * flow with refresh tokens:
 *
 *   1. {@see authorizationUrl} -> the employee's browser is sent to Google.
 *      The opaque, single-use `state` is cached and binds the flow to that
 *      specific employee.
 *   2. Google redirects the browser to the (token-less) callback with a
 *      `code` + `state`.
 *   3. {@see completeFromCallback} verifies + consumes the `state` (CSRF and
 *      replay protection), resolves the employee from it, and trades the
 *      code for access + refresh tokens.
 *   4. {@see refreshAccessToken} transparently renews expired access tokens.
 *
 * Security posture:
 * - The browser callback carries no Bearer token (it is a top-level
 *   navigation), so identity is established by the signed single-use state,
 *   never by the team's shared login. A team token can therefore never be
 *   used to attach a personal calendar to an employee.
 * - The client secret and all tokens travel only over HTTPS to Google and
 *   are stored encrypted at rest. This class never returns tokens in a form
 *   a controller would serialize to a response.
 * - `state` is single-use and short-TTL to prevent CSRF and replay.
 */
class GoogleOAuthService
{
    /**
     * How long a pending OAuth flow is valid.
     */
    protected const STATE_TTL_SECONDS = 600;

    /**
     * Build the Google authorization URL for an employee.
     *
     * A fresh, single-use `state` is cached and bound to the employee id so
     * the token-less callback can later resolve who is connecting.
     *
     * @return array{url: string, state: string}
     */
    public function authorizationUrl(User $employee): array
    {
        $state = Str::random(40);

        // Bind the flow to this employee. Single-use; consumed on callback.
        Cache::put($this->stateKey($state), $employee->id, self::STATE_TTL_SECONDS);

        $query = http_build_query([
            'client_id' => config('google.client_id'),
            'redirect_uri' => config('google.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', array_merge(
                config('google.scopes'),
                ['https://www.googleapis.com/auth/userinfo.email']
            )),
            'state' => $state,
            'access_type' => config('google.access_type'),
            'prompt' => config('google.prompt'),
            'include_granted_scopes' => 'true',
        ]);

        return [
            'url' => config('google.auth_uri').'?'.$query,
            'state' => $state,
        ];
    }

    /**
     * Resolve the employee from a verified `state`, exchange the code for
     * tokens, and persist the connection.
     *
     * @return array{connection: EmployeeGoogleConnection, employee: User}
     */
    public function completeFromCallback(?string $code, ?string $state, ?string $googleError = null): array
    {
        if ($googleError !== null) {
            throw ValidationException::withMessages([
                'google' => 'Google reported an error: '.$googleError,
            ]);
        }

        $employee = $this->resolveEmployeeFromState((string) $state);

        if (blank($code)) {
            throw ValidationException::withMessages(['code' => 'Missing OAuth authorization code.']);
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post(config('google.token_uri'), [
                'client_id' => config('google.client_id'),
                'client_secret' => config('google.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('google.redirect_uri'),
            ]);

        if ($response->failed()) {
            report('Google OAuth (authorization) token exchange failed: HTTP '.$response->status());
            throw ValidationException::withMessages([
                'code' => 'Google rejected the token request. Please reconnect your Google account.',
            ]);
        }

        $body = $response->json();

        $connection = $employee->googleCalendarConnection()->updateOrCreate(
            [],
            [
                'google_email' => $body['email'] ?? null,
                'access_token' => $body['access_token'],
                'refresh_token' => $body['refresh_token'] ?? null,
                'access_token_expires_at' => now()->addSeconds((int) ($body['expires_in'] ?? 3599)),
                'status' => 'connected',
                'connected_at' => now(),
                'disconnected_at' => null,
            ]
        );

        return ['connection' => $connection, 'employee' => $employee];
    }

    /**
     * Verify a single-use `state` and resolve the bound employee.
     */
    protected function resolveEmployeeFromState(string $state): User
    {
        if ($state === '') {
            throw ValidationException::withMessages(['state' => 'Missing OAuth state parameter.']);
        }

        $key = $this->stateKey($state);
        $employeeId = Cache::get($key);

        // Consume so a replayed or forged state is useless.
        Cache::forget($key);

        if ($employeeId === null) {
            throw ValidationException::withMessages([
                'state' => 'OAuth state verification failed. Start the connection flow again.',
            ]);
        }

        $employee = User::find($employeeId);

        if ($employee === null || $employee->status !== 'active') {
            throw ValidationException::withMessages([
                'state' => 'The employee this flow was issued to is no longer active.',
            ]);
        }

        return $employee;
    }

    /**
     * Refresh an expired access token using the stored refresh token.
     * Returns the reloaded connection, or null when it cannot be refreshed
     * (revoked / disconnected / no refresh token available).
     */
    public function refreshAccessToken(EmployeeGoogleConnection $connection): ?EmployeeGoogleConnection
    {
        if (! $connection->isRefreshable()) {
            return null;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post(config('google.token_uri'), [
                'client_id' => config('google.client_id'),
                'client_secret' => config('google.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ]);

        if ($response->failed()) {
            $this->handleRefreshFailure($connection, $response->json('error', ''));

            return null;
        }

        $body = $response->json();

        $connection->forceFill([
            'access_token' => $body['access_token'],
            'access_token_expires_at' => now()->addSeconds((int) ($body['expires_in'] ?? 3599)),
            'last_synced_at' => now(),
            'status' => 'connected',
        ]);

        // A refreshed grant may or may not echo a refresh token; keep the
        // long-lived one unless Google issues a new one.
        if (! empty($body['refresh_token'])) {
            $connection->refresh_token = $body['refresh_token'];
        }

        $connection->save();

        return $connection->refresh();
    }

    /**
     * The currently valid access token for a connection, refreshing it when
     * it is stale. Returns null when a usable token cannot be produced.
     */
    public function currentAccessToken(EmployeeGoogleConnection $connection): ?string
    {
        if ($connection->accessTokenIsFresh()) {
            return $connection->access_token;
        }

        $refreshed = $this->refreshAccessToken($connection);

        return $refreshed?->access_token;
    }

    /**
     * Disconnect: revoke the live access token with Google, then clear the
     * stored credentials and mark the row disconnected. Idempotent.
     */
    public function disconnect(EmployeeGoogleConnection $connection): void
    {
        if (filled($connection->access_token)) {
            Http::asForm()->post(config('google.token_uri'), [
                'client_id' => config('google.client_id'),
                'client_secret' => config('google.client_secret'),
                'grant_type' => 'urn:ietf:params:oauth:grant-type:revocation',
                'token' => $connection->access_token,
            ]);
        }

        $connection->forceFill([
            'status' => 'disconnected',
            'disconnected_at' => now(),
            'access_token' => null,
            'refresh_token' => null,
            'access_token_expires_at' => null,
        ])->save();
    }

    protected function handleRefreshFailure(EmployeeGoogleConnection $connection, ?string $errorCode): void
    {
        // `invalid_grant` means the refresh token was revoked or the account
        // was disconnected on Google's side. Mark it so the employee re-
        // links. The raw Google body is never persisted (it may echo data).
        if ($errorCode === 'invalid_grant') {
            $connection->forceFill(['status' => 'revoked', 'disconnected_at' => now()])->save();

            return;
        }

        $connection->forceFill(['status' => 'error'])->save();
    }

    protected function stateKey(string $state): string
    {
        return 'google-oauth:state:'.$state;
    }
}
