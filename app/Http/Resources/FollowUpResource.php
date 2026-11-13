<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FollowUp
 */
class FollowUpResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
                'status' => $this->customer->status,
            ]),
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', fn () => [
                'id' => $this->team->id,
                'team_name' => $this->team->team_name,
                'team_code' => $this->team->team_code,
            ]),
            'assigned_employee_id' => $this->assigned_employee_id,
            'assigned_employee' => $this->whenLoaded('assignedEmployee', fn () => [
                'id' => $this->assignedEmployee->id,
                'name' => $this->assignedEmployee->name,
                'email' => $this->assignedEmployee->email,
            ]),
            'created_by' => $this->created_by,
            'created_by_type' => $this->created_by_type,
            'title' => $this->title,
            'description' => $this->description,
            'follow_up_type' => $this->follow_up_type,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'status' => $this->status,
            'outcome' => $this->outcome,
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'priority' => $this->priority,
            'order_id' => $this->order_id,
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'order_status' => $this->order->order_status,
            ]),
            // Module 3 sync bookkeeping (event id + state only — never
            // tokens or credentials).
            'google_event_id' => $this->google_event_id,
            'calendar_sync_status' => $this->calendar_sync_status,
            'calendar_synced_at' => $this->calendar_synced_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
