<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'payment_method' => $this->payment_method,
            'transaction_reference' => $this->transaction_reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_date' => $this->payment_date?->toIso8601String(),
            'payment_status' => $this->payment_status,
            'source' => $this->source,
            'verified' => $this->isVerified(),
            'verified_by_admin_id' => $this->verified_by_admin_id,
            'verified_by_admin' => $this->when(
                $this->relationLoaded('verifiedByAdmin'),
                $this->verifiedByAdmin?->only(['id', 'name', 'email'])
            ),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'admin_notes' => $this->admin_notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
