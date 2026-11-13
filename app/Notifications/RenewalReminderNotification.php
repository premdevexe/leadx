<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Notification;

class RenewalReminderNotification extends Notification
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
            'type' => 'subscription.renewal_due',
            'subscription_id' => $sub->id,
            'team_id' => $sub->team_id,
            'renewal_date' => $sub->renewal_date?->toIso8601String(),
        ];
    }
}
