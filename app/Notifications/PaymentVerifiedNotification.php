<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\SalesOrder;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the order's owning team/employee when an admin verifies a
 * payment. Also mirrored into the customer notification log when a CRM
 * customer is resolvable.
 */
class PaymentVerifiedNotification extends Notification
{
    public function __construct(protected Payment $payment)
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
        $order = $this->payment->order;

        return [
            'type' => 'payment.verified',
            'payment_id' => $this->payment->id,
            'order_number' => $order?->order_number,
            'team_id' => $order?->team_id,
            'amount' => Money::format(Money::toCents($this->payment->amount)),
            'method' => $this->payment->payment_method,
            'verified_at' => $this->payment->verified_at?->toIso8601String(),
        ];
    }
}
