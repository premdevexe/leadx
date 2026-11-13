<?php

namespace App\Http\Requests\Admin;

use App\Models\Team;
use App\Support\TeamCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateTeamRequest extends FormRequest
{
    /**
     * Teams being edited should be able to validate themselves.
     */
    public function authorize(): bool
    {
        return $this->user('sanctum') instanceof \App\Models\Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('team');

        $teamCodeRules = [
            'sometimes',
            'required',
            'string',
            'max:50',
            'regex:' . TeamCode::PATTERN,
        ];

        if ($team->hasOrders()) {
            // Once a team has issued its first order the code is baked into
            // every order number, so only the current value is accepted.
            $teamCodeRules[] = Rule::in([$team->team_code]);
        } else {
            $teamCodeRules[] = Rule::unique('teams', 'team_code')->ignore($team->id);
        }

        return [
            'team_code' => $teamCodeRules,
            'team_name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('teams', 'username')->ignore($team->id)],
            'password' => ['sometimes', 'required', 'confirmed', Password::min(8)],
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
