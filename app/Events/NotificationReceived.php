<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $title;
    public string $message;
    public ?string $action;
    public ?int $orderId;
    public ?string $orderNumber;
    public string $type;

    public function __construct(User $user, array $data)
    {
        $this->userId = $user->id;
        $this->title = $data['title'] ?? 'Notification';
        $this->message = $data['message'] ?? '';
        $this->action = $data['action'] ?? null;
        $this->orderId = $data['order_id'] ?? null;
        $this->orderNumber = $data['order_number'] ?? null;
        $this->type = $data['type'] ?? 'general';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}.notifications")];
    }

    public function broadcastAs(): string
    {
        return 'notification.received';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'title' => $this->title,
            'message' => $this->message,
            'action' => $this->action,
            'order_id' => $this->orderId,
            'order_number' => $this->orderNumber,
            'type' => $this->type,
            'created_at' => now()->diffForHumans(),
            'created_at_iso' => now()->toIso8601String(),
        ];
    }
}
