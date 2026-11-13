<?php

namespace App\Http\Requests\FollowUp;

use App\Models\FollowUp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared rules for the follow-up state commands: complete, reschedule,
 * cancel. Each controller passes the subset it cares about.
 */
class UpdateFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Management is asserted per-actor in the service; here we only
        // require a recognized role on the token.
        $actor = $this->user('sanctum');

        return $actor instanceof \App\Models\Admin
            || $actor instanceof \App\Models\Team
            || $actor instanceof \App\Models\User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // The reschedule endpoint additionally requires scheduled_at;
            // it is validated in the controller (an action-specific rule).
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'next_follow_up_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
