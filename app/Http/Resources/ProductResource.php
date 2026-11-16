<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'minimum_closing_price' => (float) ($this->minimum_closing_price ?? 0),
            'duration' => $this->duration,
            'duration_label' => \App\Models\SalesOrder::DURATIONS[$this->duration] ?? $this->duration,
            'api_key_required' => (bool) $this->api_key_required,
            'status' => $this->status,
            'orders_count' => $this->orders_count ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
