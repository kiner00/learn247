<?php

namespace App\Actions\Chat;

use App\Models\Community;
use App\Models\Message;
use App\Models\User;

class SendChatMessage
{
    public function execute(User $user, Community $community, string $content): Message
    {
        $message = Message::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => $content,
        ]);

        $community->members()->where('user_id', $user->id)->update([
            'messages_last_read_at' => now(),
        ]);

        return $message->load('user:id,name,username,avatar');
    }
}
