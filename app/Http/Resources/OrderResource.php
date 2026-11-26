<?php

namespace App\Http\Resources;

use App\Models\SalesOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SalesOrder
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'team_id' => $this->team_id,
            'team_code_snapshot' => $this->team_code_snapshot,
            'team_name' => $this->team?->team_name,
            'assigned_employee_id' => $this->assigned_employee_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            // Catalog reference + frozen snapshots (derived mirrors from primary line item).
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_name_snapshot' => $this->product_name_snapshot ?? $this->product_name,
            'product_price_snapshot' => $this->product_price_snapshot ?? $this->product_value,
            'product_duration_snapshot' => $this->product_duration_snapshot ?? $this->duration,
            'duration' => $this->duration,
            'product_value' => $this->product_value,
            'discount_amount' => $this->discount_amount,
            'closing_value' => $this->closing_value,
            'amount_received' => $this->amount_received,
            'payment_status' => $this->payment_status,
            'order_status' => $this->order_status,
            'legacy_flag' => $this->legacy_flag,
            'items' => $items->map(fn (SalesOrderItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name_snapshot' => $item->product_name_snapshot,
                'product_price_snapshot' => $item->product_price_snapshot,
                'product_duration_snapshot' => $item->product_duration_snapshot,
                'minimum_closing_price_snapshot' => $item->minimum_closing_price_snapshot,
                'api_key_required_snapshot' => (bool) $item->api_key_required_snapshot,
                'quantity' => (int) $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->values()->all(),
            'subscription' => new SubscriptionResource($this->when(
                $this->relationLoaded('subscription'),
                $this->subscription
            )),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
