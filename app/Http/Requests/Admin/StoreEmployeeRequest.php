<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['sometimes', 'in:employee'],
            'status' => ['sometimes', 'in:active,inactive'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
        ];
    }
}
