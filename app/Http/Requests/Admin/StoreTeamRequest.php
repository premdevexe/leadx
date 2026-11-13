<?php

namespace App\Http\Requests\Admin;

use App\Support\TeamCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTeamRequest extends FormRequest
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
            'team_code' => [
                'required',
                'string',
                'max:50',
                'regex:' . TeamCode::PATTERN,
                'unique:teams,team_code',
            ],
            'team_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:teams,username'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'status' => ['sometimes', 'in:active,inactive'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messages(): array
    {
        return [
            'team_code.regex' => 'Team Code must match: ' . TeamCode::formatHint(),
        ];
    }
}
