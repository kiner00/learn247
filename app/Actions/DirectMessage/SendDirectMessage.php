<?php

namespace App\Actions\DirectMessage;

use App\Models\DirectMessage;
use App\Models\User;

class SendDirectMessage
{
    public function execute(User $sender, User $receiver, string $content): DirectMessage
    {
        return DirectMessage::create([
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'content'     => $content,
        ]);
    }
}
