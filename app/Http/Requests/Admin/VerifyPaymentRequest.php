<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPaymentRequest extends FormRequest
{
    /**
     * Only an authenticated, active admin token may verify a payment.
     */
    public function authorize(): bool
    {
        $actor = $this->user('sanctum');

        // Admins have no status column (always active); the token's
        // ensure-role-active middleware already guards this.
        return $actor instanceof Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
