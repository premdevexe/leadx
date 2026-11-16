<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the employee profile page (Section 8.4).
     */
    public function show(Request $request): View
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();
        $employee->loadMissing(['team', 'preset']);

        return view('employee.profile', [
            'employee' => $employee,
        ]);
    }

    /**
     * Update employee personal/contact details.
     * Strictly disallows altering role, team, preset, status, or permissions.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        // Strictly sanitize and validate only allowed fields
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee->id),
            ],
        ]);

        // Guard against any injected attributes
        $safeAttributes = [
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'],
        ];

        $employee->update($safeAttributes);

        return back()->with('status', 'Profile details updated successfully.');
    }

    /**
     * Update employee password with current password verification.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $employee */
        $employee = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

        $employee->update([
            'password' => Hash::make($validated['password']),
        ]);

        // A password change revokes every previously-issued API token,
        // except the one that performed this very request (so the session
        // doing the change stays valid). With no current token (normal web
        // flow) this deletes all of the employee's tokens.
        $currentTokenId = $employee->currentAccessToken()?->id;

        $employee->tokens()->where('id', '!=', $currentTokenId ?? 0)->delete();

        return back()->with('status', 'Password changed successfully.');
    }
}
