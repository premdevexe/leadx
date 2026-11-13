<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class WhatsappShareLogRequest extends FormRequest
{
    /**
     * Only the order's team, its assigned employee, or an admin may log a
     * share. Full ownership checks are enforced in the controller.
     */
    public function authorize(): bool
    {
        $actor = $this->user('sanctum');

        return $actor instanceof \App\Models\Team
            || $actor instanceof \App\Models\User
            || $actor instanceof \App\Models\Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
