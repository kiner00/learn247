<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DirectMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $senderId,
        public int $receiverId,
        public array $message,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("dm.{$this->receiverId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'sender_id' => $this->senderId,
            'message'   => $this->message,
        ];
    }
}
