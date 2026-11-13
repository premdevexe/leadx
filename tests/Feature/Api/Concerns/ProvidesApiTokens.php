<?php

namespace Tests\Feature\Api\Concerns;

use App\Models\Admin;
use App\Models\Team;
use App\Models\User;

/**
 * Helper to mint real Sanctum tokens through the app's own login endpoints
 * (or fall back to plain createTokenIfNone) for the three roles.
 */
trait ProvidesApiTokens
{
    protected function adminToken(Admin $admin): string
    {
        return $admin->createToken('test')->plainTextToken;
    }

    protected function teamToken(Team $team): string
    {
        return $team->createToken('test')->plainTextToken;
    }

    protected function employeeToken(User $employee): string
    {
        return $employee->createToken('test')->plainTextToken;
    }

    protected function actingAsToken(string $token): array
    {
        auth()->forgetGuards();

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}
