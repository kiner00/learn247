<?php

namespace App\Actions\DirectMessage;

use App\Events\DirectMessageSent;
use App\Models\DirectMessage;
use App\Models\User;

class SendDirectMessage
{
    public function execute(User $sender, User $receiver, string $content): DirectMessage
    {
        $message = DirectMessage::create([
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'content'     => $content,
        ]);

        DirectMessageSent::dispatch($sender->id, $receiver->id, [
            'id'         => $message->id,
            'content'    => $message->content,
            'is_mine'    => false,
            'created_at' => $message->created_at,
        ]);

        return $message;
    }
}
