<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Notifications\Notification;

/**
 * The team-only "expires tomorrow" reminder.
 *
 * Delivered via the `database` channel to each *individual active team
 * member* of the subscription's team (resolved at dispatch time, so
 * inactives and unrelated teams never receive it). The body matches the
 * delivery spec exactly:
 *
 *   Subscription Expiry Reminder
 *   Customer: [Customer Name]
 *   Order ID: [Order ID]
 *   Product: [Product Name]
 *   Expiry Date: [Expiry Date]
 *   This subscription expires tomorrow. Please follow up with the
 *   customer for renewal.
 *
 * It is NOT sent to customers, public users, or any team other than the
 * subscription's own.
 */
class SubscriptionExpiresTomorrowNotification extends Notification
{
    public function __construct(protected Subscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        // Database notifications only; email is left out unless a deployment
        // adds it here later (the spec says email is optional).
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
            'type' => 'subscription.expires_tomorrow',
            'subscription_id' => $sub->id,
            'order_number' => $order?->order_number,
            'team_id' => $sub->team_id,
            'customer_name' => $order?->customer_name,
            'product_name' => $order?->product_name_snapshot ?? $order?->product_name,
            'expires_at' => $sub->expires_at?->toIso8601String(),
            'message' => $this->messageBody($sub, $order),
        ];
    }

    /**
     * The human-readable reminder text (also surfaced to the UI verbatim).
     */
    public function messageBody(Subscription $sub, ?\App\Models\SalesOrder $order): string
    {
        $customer = $order?->customer_name ?? 'Unknown customer';
        $orderNumber = $order?->order_number ?? ('#'.$sub->id);
        $product = $order?->product_name_snapshot ?? $order?->product_name ?? '—';
        $expiry = $sub->expires_at?->format('d F Y') ?? 'n/a';

        return "Subscription Expiry Reminder\n\n"
            ."Customer: {$customer}\n"
            ."Order ID: {$orderNumber}\n"
            ."Product: {$product}\n"
            ."Expiry Date: {$expiry}\n\n"
            ."This subscription expires tomorrow. Please follow up with the customer for renewal.";
    }
}
