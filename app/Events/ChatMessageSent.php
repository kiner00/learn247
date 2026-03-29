<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $communityId,
        public array $message,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("community.{$this->communityId}.chat"),
        ];
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
