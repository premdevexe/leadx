<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public view of a Google Calendar connection.
 *
 * Module 2 rule: never expose OAuth credentials or tokens through API
 * responses. This resource intentionally omits access_token and
 * refresh_token entirely; the connection is identified by the (optional,
 * user-visible) Google email and its status.
 *
 * @mixin \App\Models\EmployeeGoogleConnection
 */
class EmployeeGoogleConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'connected' => $this->status === 'connected',
            'status' => $this->status,
            'google_email' => $this->google_email,
            'google_user_id' => $this->google_user_id,
            'calendar_id' => $this->calendar_id,
            'access_token_expires_at' => $this->access_token_expires_at?->toIso8601String(),
            'connected_at' => $this->connected_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
