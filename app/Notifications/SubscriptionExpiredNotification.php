<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification
{
    public function __construct(protected Subscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $sub = $this->subscription;

        return [
            'type' => 'subscription.expired',
            'subscription_id' => $sub->id,
            'team_id' => $sub->team_id,
            'expired_at' => $sub->expires_at?->toIso8601String(),
        ];
    }
}
