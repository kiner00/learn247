<?php

namespace App\Actions\Chat;

use App\Models\Community;
use App\Models\Message;
use App\Models\User;
use App\Services\TelegramService;

class SendChatMessage
{
    public function __construct(private TelegramService $telegram) {}

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

        if ($community->telegram_bot_token && $community->telegram_chat_id) {
            $this->telegram->sendMessage(
                $community->telegram_bot_token,
                $community->telegram_chat_id,
                "<b>{$user->name}</b>: " . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        return $message->load('user:id,name,username,avatar');
    }
}
