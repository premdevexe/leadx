<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class AuthenticationService
{
    /**
     * Attempt a super-admin login and return a Sanctum token.
     */
    public function admin(string $login, string $password, string $ip): NewAccessToken
    {
        $key = "sanctum-admin:{$ip}";
        $this->throttle($key);

        $admin = Admin::where('username', $login)->orWhere('email', $login)->first();

        if (! $admin || ! Hash::check($password, $admin->password)) {
            RateLimiter::hit($key, 900);
            $this->throwCredentialError();
        }

        RateLimiter::clear($key);

        return $admin->createToken('leadx-admin');
    }

    /**
     * Attempt a team login and return a Sanctum token.
     */
    public function team(string $login, string $password, string $ip): NewAccessToken
    {
        $key = "sanctum-team:{$ip}";
        $this->throttle($key);

        $team = Team::where('username', $login)->first();

        if (! $team || ! Hash::check($password, $team->password)) {
            RateLimiter::hit($key, 900);
            $this->throwCredentialError();
        }

        if ($team->status !== 'active') {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages([
                'login' => 'This team account is inactive.',
            ]);
        }

        RateLimiter::clear($key);

        return $team->createToken('leadx-team');
    }

    /**
     * Attempt an individual employee login and return a Sanctum token.
     */
    public function employee(string $login, string $password, string $ip): NewAccessToken
    {
        $key = "sanctum-employee:{$ip}";
        $this->throttle($key);

        $user = User::where('username', $login)->orWhere('email', $login)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 900);
            $this->throwCredentialError();
        }

        if ($user->status !== 'active') {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages([
                'login' => 'This employee account is inactive.',
            ]);
        }

        RateLimiter::clear($key);

        return $user->createToken('leadx-employee');
    }

    /**
     * Revoke the caller's current token on logout.
     */
    public function revoke(Model $model): void
    {
        $model->currentAccessToken()->delete();
    }

    protected function throttle(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'Too many login attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }
    }

    protected function throwCredentialError(): never
    {
        throw ValidationException::withMessages([
            'login' => 'The provided credentials do not match our records.',
        ]);
    }
}
