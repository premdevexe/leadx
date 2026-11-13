<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [];
        }

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'team_id' => $this->team_id,
            'customer_id' => $this->customer_id,
            'product_id' => $this->product_id,
            'plan_id' => $this->plan_id,
            'billing_cycle' => $this->billing_cycle,
            'plan_snapshot' => $this->plan_snapshot,
            'price_snapshot' => $this->price_snapshot,
            'amount_paid' => $this->amount_paid,
            'renewal_date' => $this->renewal_date?->toIso8601String(),
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
