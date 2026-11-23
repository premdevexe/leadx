<?php

namespace App\Services;

use App\Models\EmployeeDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Cloud Messaging sender for the Flutter employee app.
 *
 * Dependency-free on purpose: this PHP build (8.2, no guaranteed `ext-sodium`)
 * cannot host `kreait/firebase-php` cleanly, so the service-account -> FCM
 * path is implemented directly with `openssl` + Laravel's `Http` client (both
 * always present). No new composer package is introduced.
 *
 * Security model:
 * - The service-account JSON is referenced by a file path in .env
 *   (`FCM_CREDENTIALS_PATH`). The file lives OUTSIDE the deployed codebase on
 *   the server and is never committed.
 * - The private key is read server-side only. A short-lived (60-min) OAuth
 *   JWT is exchanged for an access token that authorizes the FCM call.
 * - Nothing here ever writes the private key, JWT, or access token to logs.
 */
class FcmService
{
    /**
     * OAuth2 token endpoint for a service account.
     */
    protected const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * OAuth scope required to send FCM messages.
     */
    protected const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    protected ?string $accessToken = null;

    protected ?int $tokenExpiresAt = null;

    /**
     * Whether FCM is usable on this installation (credentials file present).
     */
    public function isConfigured(): bool
    {
        return $this->serviceAccount() !== null;
    }

    /**
     * Send a high-priority FCM notification to one or more device tokens.
     *
     * Dead tokens (FCM answers NOT_FOUND) are marked inactive in the database
     * so they stop being retried, and are reported back so the caller can
     * clean up. Transient errors are surfaced for the queued job to retry.
     *
     * @param  array<int, string>  $fcmTokens
     * @param  array<string, string>  $data  data-payload keys -> navigation hints
     * @return array{sent: int, failed: int, removed: int, configured: bool}
     */
    public function send(array $fcmTokens, string $title, string $body, array $data = [], ?string $deduplicationKey = null): array
    {
        $configured = $this->isConfigured();

        if (! $configured || empty($fcmTokens)) {
            return ['sent' => 0, 'failed' => 0, 'removed' => 0, 'configured' => $configured];
        }

        $accessToken = $this->getAccessToken();

        if ($accessToken === null) {
            Log::warning('FCM send skipped: no valid access token for the service account.');

            return ['sent' => 0, 'failed' => 0, 'removed' => 0, 'configured' => $configured];
        }

        $result = ['sent' => 0, 'failed' => 0, 'removed' => 0, 'configured' => $configured];

        foreach ($fcmTokens as $token) {
            $outcome = $this->sendToToken($accessToken, $token, $title, $body, $data, $deduplicationKey);

            if ($outcome['ok']) {
                $result['sent']++;
                EmployeeDeviceToken::where('fcm_token', $token)->update(['last_used_at' => now()]);
            } elseif ($outcome['invalid']) {
                $result['removed']++;
                $result['failed']++;
                EmployeeDeviceToken::where('fcm_token', $token)
                    ->update(['is_active' => false, 'last_error_at' => now()]);
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Send a single message to one token.
     *
     * @param  array<string, string>  $data
     * @return array{ok: bool, invalid: bool, transient: bool}
     */
    protected function sendToToken(string $accessToken, string $token, string $title, string $body, array $data, ?string $deduplicationKey): array
    {
        $project = config('services.fcm.project_id') ?: 'leadx-eb5c5';
        $endpoint = 'https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send';

        $stringData = [];
        foreach ($data as $k => $v) {
            $stringData[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        $channelId = (string) (config('services.fcm.android_channel_id') ?: 'leadx_general');
        if (($data['type'] ?? '') === 'closing_value' || ($data['type'] ?? '') === 'payment') {
            $channelId = 'leadx_high_priority';
        }

        $message = [
            'token' => $token,
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'channel_id' => $channelId,
                    'default_sound' => true,
                    'default_vibrate_timings' => true,
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ],
        ];

        if ($title !== '' || $body !== '') {
            $message['notification'] = array_filter([
                'title' => $title !== '' ? $title : 'LeadX',
                'body' => $body,
            ], fn ($v) => $v !== '');
        }

        if (! empty($stringData)) {
            $message['data'] = $stringData;
        }

        $payload = [
            'message' => $message,
            'validate_only' => false,
        ];

        try {
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->asJson()
                ->post($endpoint, $payload);

            $status = $response->status();

            if ($status >= 200 && $status < 300) {
                return ['ok' => true, 'invalid' => false, 'transient' => false];
            }

            // 400/404 NOT_FOUND -> the registration token is no longer valid.
            $invalid = ($status === 400 || $status === 404)
                && str_contains((string) $response->body(), 'NOT_FOUND');

            // 429 / 5xx are retryable.
            $transient = $status === 429 || $status >= 500;

            if (! $transient) {
                Log::warning('FCM rejected push notification', [
                    'status' => $status,
                    'token_prefix' => substr($token, 0, 8),
                    'error' => $this->firstError($response->json('error') ?? null),
                ]);
            }

            return ['ok' => false, 'invalid' => $invalid, 'transient' => $transient];
        } catch (Throwable $e) {
            // Network failure: transient; the queued job will retry.
            Log::warning('FCM send threw an exception', [
                'token_prefix' => substr($token, 0, 8),
                'error' => substr($e->getMessage(), 0, 200),
            ]);

            return ['ok' => false, 'invalid' => false, 'transient' => true];
        }
    }

    /**
     * Obtain (and cache) a short-lived FCM access token for the service account.
     */
    protected function getAccessToken(): ?string
    {
        if ($this->accessToken !== null && ($this->tokenExpiresAt ?? 0) > time() + 30) {
            return $this->accessToken;
        }

        $sa = $this->serviceAccount();

        if ($sa === null) {
            return null;
        }

        $privateKey = (string) ($sa['private_key'] ?? '');
        $clientEmail = (string) ($sa['client_email'] ?? '');

        if ($privateKey === '' || $clientEmail === '') {
            return null;
        }

        $jwt = $this->createServiceAccountJwt($privateKey, $clientEmail, time());

        if ($jwt === null) {
            return null;
        }

        $response = Http::post(
            self::TOKEN_ENDPOINT,
            [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]
        );

        $data = $response->json();

        if (! is_array($data) || empty($data['access_token'])) {
            Log::warning('Failed to obtain FCM OAuth token from service account.', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $this->accessToken = (string) $data['access_token'];
        $this->tokenExpiresAt = time() + (int) ($data['expires_in'] ?? 3599);

        return $this->accessToken;
    }

    /**
     * Build and sign the service-account assertion JWT (RS256).
     */
    protected function createServiceAccountJwt(string $privateKey, string $clientEmail, int $iat): ?string
    {
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false) {
            return null;
        }

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $clientEmail,
            'scope' => self::FCM_SCOPE,
            'aud' => self::TOKEN_ENDPOINT,
            'iat' => $iat,
            'exp' => $iat + 3599,
            'sub' => $clientEmail,
        ];

        $encoded = $this->b64url(json_encode($header)).'.'.$this->b64url(json_encode($claims));
        $signature = '';

        $signed = openssl_sign($encoded, $signature, $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);

        if (! $signed) {
            return null;
        }

        return $encoded.'.'.$this->b64url($signature);
    }

    protected function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Load the service-account credentials file.
     *
     * @return array<string, mixed>|null
     */
    protected function serviceAccount(): ?array
    {
        $path = config('services.fcm.credentials_path');

        if (empty($path) || ! is_string($path)) {
            return null;
        }

        $real = realpath($path);

        if ($real === false || ! is_file($real)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($real), true);

        return is_array($json) ? $json : null;
    }

    protected function firstError(mixed $errorNode): ?string
    {
        if (is_array($errorNode)) {
            $details = $errorNode['details'][0] ?? null;

            if (is_array($details) && isset($details['description'])) {
                return (string) $details['description'];
            }

            if (isset($errorNode['message'])) {
                return (string) $errorNode['message'];
            }

            return json_encode($errorNode);
        }

        return is_string($errorNode) ? $errorNode : null;
    }
}
