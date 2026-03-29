<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $communityId,
        public int $messageId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("community.{$this->communityId}.chat"),
        ];
    }

    public function broadcastWith(): array
    {
        return ['message_id' => $this->messageId];
    }
}
