<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FollowUpHistory
 */
class FollowUpHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'outcome' => $this->outcome,
            'notes' => $this->notes,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'acted_by' => $this->acted_by,
            'acted_by_type' => $this->acted_by_type,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
