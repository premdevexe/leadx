<?php

namespace App\Notifications;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GeneralOrderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?SalesOrder $order = null,
        public string $action = 'general'
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'order_id' => $this->order?->id,
            'order_number' => $this->order?->order_number,
            'action' => $this->action,
        ];
    }
}
