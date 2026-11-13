<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'team_id' => $this->team_id,
            'purchases_count' => $this->purchases_count,
            'total_purchased' => $this->total_purchased,
            'last_purchase_at' => $this->last_purchase_at?->toIso8601String(),
        ];
    }
}
