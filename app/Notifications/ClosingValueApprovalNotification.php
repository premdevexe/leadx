<?php

namespace App\Notifications;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClosingValueApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SalesOrder $order,
        public string $action, // 'accepted' or 'rejected'
        public ?string $notes = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'action' => $this->action,
            'title' => $this->action === 'accepted' ? 'Closing Value Approved' : 'Closing Value Rejected',
            'message' => $this->action === 'accepted'
                ? "Super Admin approved the closing value for order {$this->order->order_number}."
                : "Super Admin rejected the closing value for order {$this->order->order_number}. Reason: {$this->notes}",
            'notes' => $this->notes,
        ];
    }
}
