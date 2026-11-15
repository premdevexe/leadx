<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateEmployeeWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $employeeId = $request->session()->get('employee_id');

        if (! $employeeId) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('employee.login');
        }

        $employee = User::find($employeeId);

        if (! $employee || ($employee->status ?? 'active') !== 'active' || ($employee->role ?? '') !== 'employee') {
            $request->session()->forget('employee_id');
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This employee account is no longer active.'], 403);
            }
            return redirect()->route('employee.login')->withErrors(['login' => 'Your employee account has been disabled.']);
        }

        $request->setUserResolver(fn () => $employee);

        return $next($request);
    }
}
