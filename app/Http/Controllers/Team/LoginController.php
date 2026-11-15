<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function showLoginForm(): RedirectResponse
    {
        return redirect()->route('employee.login')
            ->with('info', 'Team login has been deprecated. Please log in with your individual employee account.');
    }

    public function login(Request $request): RedirectResponse
    {
        return redirect()->route('employee.login')
            ->with('info', 'Team login has been deprecated. Please log in with your individual employee account.')
            ->withErrors(['login' => 'Team login has been deprecated. Please log in with your individual employee account.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        if (\Illuminate\Support\Facades\Auth::guard('team')->check()) {
            \Illuminate\Support\Facades\Auth::guard('team')->logout();
        }

        $request->session()->forget('team_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('employee.login')
            ->with('info', 'Team login has been deprecated. Please log in with your individual employee account.');
    }
}

