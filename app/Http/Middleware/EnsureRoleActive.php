<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleActive
{
    /**
     * Roles accepted by this middleware and the model they map to.
     *
     * @var array<string, class-string>
     */
    protected const ROLE_MODELS = [
        'admin' => Admin::class,
        'team' => Team::class,
        'employee' => User::class,
    ];

    /**
     * Ensure the authenticated token belongs to the expected role model
     * and that the account is still active.
     *
     * Usage: `auth:sanctum, ensure-role-active:admin`
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $expected = self::ROLE_MODELS[$role] ?? null;

        if (! $expected) {
            return response()->json(['message' => 'Unknown role.'], 403);
        }

        $user = $request->user('sanctum');

        // A team token must never resolve on an admin route (or vice-versa).
        if (! $user instanceof $expected) {
            return response()->json(['message' => 'This token is not valid for the requested resource.'], 403);
        }

        // Verify the account is still enabled; disabled accounts lose access
        // even though their tokens technically still exist. Admins (the
        // pre-existing model) have no status column and default to active.
        if (($user->status ?? 'active') !== 'active') {
            $user->tokens()->delete();

            return response()->json(['message' => 'This account has been disabled.'], 403);
        }

        return $next($request);
    }
}
