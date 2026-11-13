<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Support\Money;
use Illuminate\Notifications\Notification;

class SubscriptionActivatedNotification extends Notification
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
        $order = $sub->order;

        return [
            'type' => 'subscription.activated',
            'subscription_id' => $sub->id,
            'order_number' => $order?->order_number,
            'team_id' => $sub->team_id,
            'plan' => $sub->plan_snapshot ?? $order?->subscription_plan,
            'expires_at' => $sub->expires_at?->toIso8601String(),
            'amount_paid' => Money::format(Money::toCents($sub->amount_paid)),
        ];
    }
}
