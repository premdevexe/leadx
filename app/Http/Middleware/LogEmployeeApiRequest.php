<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs employee API requests to the audit log — without ever writing a
 * secret to the row.
 *
 * What is logged: method, path (ids already scrubbed), status, duration,
 * ip (masked), actor. What is deliberately NOT logged: Authorization
 * headers, tokens, api_key fields, passwords, and request bodies — none of
 * which are captured at all.
 *
 * Only API routes under /api/v1/employee are covered; a log failure must
 * never break a request, so errors are swallowed.
 */
class LogEmployeeApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! str_starts_with($request->path(), 'api/v1/employee')) {
            return $next($request);
        }

        $start = microtime(true);
        $response = $next($request);

        try {
            $actor = $request->user('sanctum');

            // AuditLog casts new_values to array (never a raw string).
            $maskedIp = $this->maskIp($request->ip());

            AuditLog::create([
                'auditable_type' => 'api_request',
                'auditable_id' => 0,
                'event' => 'api.employee.request',
                'new_values' => [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status' => $response->getStatusCode(),
                    'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                    'actor_type' => $actor ? class_basename($actor) : null,
                    'actor_id' => $actor?->getKey(),
                    'ip' => $maskedIp,
                ],
                'ip' => $maskedIp,
            ]);
        } catch (\Throwable) {
            // Never fail the request because of logging.
        }

        return $response;
    }

    /**
     * Keep an ip address out of logs in a form that is still useful for
     * forensics but is not the raw client address.
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

        // IPv6: keep the first 4 groups only.
        $parts = explode(':', $ip);

        return implode(':', array_slice($parts, 0, 4)).':xxx';
    }
}
