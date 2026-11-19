<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Employee self-service profile update (PUT /api/v1/employee/profile).
 *
 * Only personal/contact fields are updatable — strictly matching the web
 * panel's ProfileController::update (name, phone, email). Role, team,
 * preset, status and permissions are admin-managed and are not accepted
 * here. Password change is optional (current_password verified in the
 * controller against the caller's hash — the `current_password` rule
 * resolves the web guard, which is not the identity source for a Sanctum
 * API token, so verification happens explicitly).
 */
class EmployeeProfileUpdateRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        $actor = $this->user('sanctum');

        return $actor instanceof \App\Models\User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\User $employee */
        $employee = $this->user('sanctum');

        return [
            // "Sometimes required": a field may be omitted (and is then left
            // unchanged), but if sent it must be valid and non-empty.
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($employee->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee->id),
            ],
            'current_password' => ['sometimes', 'nullable', 'string'],
            'password' => ['sometimes', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ];
    }
}
