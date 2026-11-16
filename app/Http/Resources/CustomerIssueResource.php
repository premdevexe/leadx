<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CrmCustomerIssue
 */
class CustomerIssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'issue_title' => $this->issue_title,
            'issue_description' => $this->issue_description,
            'status' => $this->status,
            'issue_date' => $this->issue_date?->toDateString(),
            'history' => $this->when(
                $this->relationLoaded('histories'),
                $this->histories->map(fn ($h) => [
                    'old_status' => $h->old_status,
                    'new_status' => $h->new_status,
                    'update_note' => $h->update_note,
                    'created_at' => $h->created_at?->toIso8601String(),
                ])->all()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
