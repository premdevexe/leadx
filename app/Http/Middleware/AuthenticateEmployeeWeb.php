<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateEmployeeWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $employee */
        $employee = Auth::guard('employee')->user();

        if (! $employee && Auth::guard('web')->check()) {
            $webUser = Auth::guard('web')->user();
            if ($webUser instanceof User && ($webUser->role ?? '') === 'employee') {
                $employee = $webUser;
                Auth::guard('employee')->setUser($employee);
                $request->session()->put('employee_id', $employee->id);
            }
        }

        if (! $employee && $request->session()->has('employee_id')) {
            $employee = User::find($request->session()->get('employee_id'));
            if ($employee) {
                Auth::guard('employee')->setUser($employee);
            }
        }

        if (! $employee) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->guest(route('employee.login'));
        }

        if (($employee->status ?? 'active') !== 'active' || ($employee->role ?? '') !== 'employee') {
            Auth::guard('employee')->logout();
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
