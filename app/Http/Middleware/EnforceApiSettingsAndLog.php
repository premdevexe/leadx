<?php

namespace App\Http\Middleware;

use App\Models\ApiApplication;
use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceApiSettingsAndLog
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce for API routes
        if (! str_starts_with($request->path(), 'api')) {
            return $next($request);
        }

        // Safety check if database table is migrated
        try {
            if (! Schema::hasTable('api_applications')) {
                return $next($request);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        $app = ApiApplication::leadx();

        // 1. Maintenance Mode
        if ($app->maintenance_mode) {
            return response()->json([
                'status' => 'error',
                'message' => 'API is currently in maintenance mode. Please try again later.',
                'code' => 503,
            ], 503);
        }

        // 2. API Status (Enabled / Disabled)
        if (! $app->is_enabled) {
            return response()->json([
                'status' => 'error',
                'message' => 'API access is currently disabled.',
                'code' => 503,
            ], 503);
        }

        // 3. IP Allowlist
        if (! $app->isIpAllowed($request->ip())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your IP address is not authorized to access this API.',
                'code' => 403,
            ], 403);
        }

        // 4. Rate Limiting
        if ($app->rate_limit_per_minute > 0) {
            $clientIp = $request->ip() ?: 'unknown';
            $rateKey = 'leadx_api_rate:' . md5($clientIp);
            $currentHits = (int) Cache::get($rateKey, 0);

            if ($currentHits >= $app->rate_limit_per_minute) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rate limit exceeded. Maximum ' . $app->rate_limit_per_minute . ' requests per minute.',
                    'code' => 429,
                ], 429);
            }

            // Increment hit counter for 60 seconds window
            if ($currentHits === 0) {
                Cache::put($rateKey, 1, 60);
            } else {
                Cache::increment($rateKey);
            }
        }

        // 5. Track API Key Usage if X-API-KEY header or query param is provided
        $this->trackApiKeyUsage($request);

        // 6. Measure execution duration
        $start = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        // 7. Safe request logging (never logs passwords, secrets or request payload)
        $requestId = 'req_' . Str::random(16);
        $this->logApiRequest($request, $response, $durationMs, $requestId);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    /**
     * Track last_used_at timestamp on active API key.
     */
    protected function trackApiKeyUsage(Request $request): void
    {
        try {
            $apiKeyHeader = $request->header('X-API-KEY') ?: $request->header('x-api-key');
            if ($apiKeyHeader) {
                $hash = hash('sha256', trim($apiKeyHeader));
                $key = ApiKey::where('key_hash', $hash)->where('status', ApiKey::STATUS_ACTIVE)->first();
                if ($key) {
                    $key->update(['last_used_at' => now()]);
                }
            }
        } catch (\Throwable) {
            // Never fail request
        }
    }

    /**
     * Persist sanitized log to database.
     */
    protected function logApiRequest(Request $request, Response $response, int $durationMs, string $requestId): void
    {
        try {
            if (! Schema::hasTable('api_request_logs')) {
                return;
            }

            ApiRequestLog::create([
                'request_id' => $requestId,
                'endpoint' => '/' . ltrim($request->path(), '/'),
                'method' => strtoupper($request->method()),
                'status_code' => $response->getStatusCode(),
                'response_time_ms' => $durationMs,
                'ip_address' => $this->maskIp($request->ip()),
                'user_agent' => Str::limit($request->userAgent() ?? '', 250),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Swallowed to prevent logging failure from breaking response
        }
    }

    /**
     * Mask IP address for privacy.
     */
    protected function maskIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        if (str_contains($ip, '.')) {
            $parts = explode('.', $ip);
            $parts[count($parts) - 1] = 'x';
            return implode('.', $parts);
        }

        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)) . ':xxxx';
        }

        return $ip;
    }
}
