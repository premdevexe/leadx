<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmin
{
    /**
     * Ensure the user is authenticated via the `admin` guard,
     * redirecting unauthenticated visitors to the admin login screen.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('admin')->check()) {
            throw new AuthenticationException(
                'Unauthenticated.',
                ['admin'],
                $request->expectsJson() ? null : route('admin.login'),
            );
        }

        return $next($request);
    }
}
