<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;

class EnsureEmployeePermission
{
    public function __construct(protected PermissionService $permissionService)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        // Web (session employee guard / default guard) OR API (Sanctum token
        // belonging to an individual employee). A team or admin token never
        // satisfies an employee-permission gate.
        $user = $request->user('sanctum') ?? auth()->guard('employee')->user() ?? auth()->user();

        if (! $user instanceof \App\Models\User
            || ! $this->permissionService->hasPermission($user, $permission)
        ) {
            if ($request->expectsJson()) {
                return response()->json(['message' => "Forbidden: Missing permission [{$permission}]."], 403);
            }

            abort(403, "You do not have permission to access this resource ({$permission}).");
        }

        return $next($request);
    }
}
