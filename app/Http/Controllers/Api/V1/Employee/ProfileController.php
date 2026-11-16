<?php

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Api\V1\Employee\Concerns\EmployeeScoped;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\EmployeeProfileUpdateRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Employee profile — mirrors the web panel's My Profile page (GET) and its
 * contact-info + password updates (PUT). The same strict rules as the web
 * controller apply: only name / phone / email are self-service; role,
 * team, preset, status and permissions are admin-managed and never
 * updatable by the employee themselves.
 */
class ProfileController extends Controller
{
    use EmployeeScoped;

    /**
     * GET /api/v1/employee/profile
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);
        $employee->loadMissing(['team', 'preset']);

        return response()->json(['data' => new EmployeeResource($employee)]);
    }

    /**
     * PUT /api/v1/employee/profile
     *
     * Accepts either a contact-info update or a password change:
     *  - { name?, phone?, email? }        — updates the profile fields
     *  - { current_password, password, password_confirmation }
     *                                        — changes the password and
     *                                          revokes every other token
     *  - both                                — applied in that order
     */
    public function update(EmployeeProfileUpdateRequest $request): JsonResponse
    {
        /** @var User $employee */
        $employee = $this->currentEmployee($request);

        $profileUpdated = false;

        if ($request->input('name') !== null || $request->input('email') !== null || $request->input('phone') !== null) {
            $validated = $request->validated();

            // Untouched fields keep their current values — only what the
            // client actually sent is written.
            $employee->update([
                'name' => $validated['name'] ?? $employee->name,
                'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $employee->phone,
                'email' => $validated['email'] ?? $employee->email,
            ]);

            $profileUpdated = true;
        }

        $passwordChanged = false;

        if ($request->filled('password')) {
            // Re-validate current_password against the live user model —
            // the request class only checks structure, not the hash.
            if (! \Illuminate\Support\Facades\Hash::check($request->input('current_password'), $employee->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'The current password does not match our records.',
                ]);
            }

            $employee->update(['password' => \Illuminate\Support\Facades\Hash::make($request->input('password'))]);

            // Same rule as the web panel: a password change revokes every
            // previously-issued token except the one performing the change,
            // so all other sessions/devices must sign in again.
            $currentTokenId = $employee->currentAccessToken()?->id;
            $employee->tokens()->where('id', '!=', $currentTokenId ?? 0)->delete();

            $passwordChanged = true;
        }

        if (! $profileUpdated && ! $passwordChanged) {
            throw ValidationException::withMessages([
                'profile' => 'No updatable fields were provided.',
            ]);
        }

        return response()->json([
            'message' => $passwordChanged && $profileUpdated
                ? 'Profile and password updated successfully.'
                : ($passwordChanged ? 'Password changed successfully.' : 'Profile details updated successfully.'),
            'data' => new EmployeeResource($employee->refresh()),
        ]);
    }
}
