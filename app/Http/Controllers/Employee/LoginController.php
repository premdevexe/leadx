<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.employee-login');
    }

    public function login(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $key = 'employee-login:'.strtolower($data['login']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Too many login attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $user = User::where('username', $data['login'])->orWhere('email', $data['login'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages([
                'login' => 'The provided credentials do not match our records.',
            ]);
        }

        if ($user->status !== 'active') {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages([
                'login' => 'This employee account is inactive.',
            ]);
        }

        if ($user->role !== 'employee') {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages([
                'login' => 'This account is not an employee.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        Auth::guard('employee')->login($user, $request->boolean('remember'));
        $request->session()->put('employee_id', $user->id);

        return redirect()->intended(route('employee.dashboard'));
    }

    public function logout(Request $request): \Illuminate\Http\RedirectResponse
    {
        Auth::guard('employee')->logout();
        $request->session()->forget('employee_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('employee.login');
    }
}
