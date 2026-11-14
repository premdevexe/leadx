<?php

namespace App\Http\Requests\Order;

use App\Models\Admin;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store or replace the customer's API key for an order.
 *
 * Exactly one field — `api_key` — matching the order panel's single input.
 * A blank submission is rejected server-side so a blank form can never wipe
 * the stored credential. Authorization (who may manage THIS order's key) is
 * enforced by {@see \App\Services\OrderCredentialService::canManage()} in the
 * controller, not here; this only validates the payload and that the actor
 * is a LeadX principal.
 */
class StoreOrderApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Two entry points hit this request: the web admin panel (session
        // `admin` guard) and the /api/v1/orders/{order}/api-key endpoint
        // (Sanctum token). Accept a LeadX principal of any of the three
        // roles; per-order access is then enforced by the service.
        $actor = $this->user('admin') ?? $this->user();

        return $actor instanceof Admin || $actor instanceof Team || $actor instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'api_key' => [
                'required',
                'string',
                'max:2048',
                // Must not be blank or whitespace-only (a bad paste); the
                // value is trimmed before storage and re-checked there.
                'regex:/^\S/u',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'api_key.required' => 'An API key is required.',
            'api_key.regex' => 'The API key may not be blank.',
        ];
    }

    public function attributes(): array
    {
        return ['api_key' => 'API key'];
    }
}
