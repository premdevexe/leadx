<?php

namespace App\Http\Requests\FollowUp;

use App\Models\Admin;
use App\Models\FollowUp;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('sanctum');

        return $actor instanceof Admin
            || $actor instanceof Team
            || $actor instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Rule 1 & 2: must reference an existing CRM customer. The
            // "verified purchase" check happens in the service (a plain
            // exists rule can't express the paying-status constraint).
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'follow_up_type' => ['sometimes', 'in:'.implode(',', FollowUp::TYPES)],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'next_follow_up_at' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', 'in:'.implode(',', FollowUp::PRIORITIES)],
            'order_id' => ['sometimes', 'nullable', 'integer', 'exists:sales_orders,id'],
            // Only active employees may be assigned; team membership is
            // enforced in the service.
            'assigned_employee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sync_to_calendar' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.exists' => 'The customer does not exist.',
            'assigned_employee_id.exists' => 'The assigned employee does not exist.',
        ];
    }
}
