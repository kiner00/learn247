<?php

namespace App\Actions\Chat;

use App\Events\ChatMessageSent;
use App\Jobs\ForwardMessageToTelegram;
use App\Models\Community;
use App\Models\Message;
use App\Models\User;

class SendChatMessage
{
    public function execute(User $user, Community $community, string $content, ?string $mediaUrl = null, ?string $mediaType = null): Message
    {
        $message = Message::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => $content,
            'media_url'    => $mediaUrl,
            'media_type'   => $mediaType,
        ]);

        $community->members()->where('user_id', $user->id)->update([
            'messages_last_read_at' => now(),
        ]);

        if ($community->telegram_bot_token && $community->telegram_chat_id) {
            $isAdmin = $community->owner_id === $user->id;
            $prefix  = $isAdmin ? "From Curzzo Admin - <b>{$user->name}</b>" : "From Curzzo Member - <b>{$user->name}</b>";
            $caption = $content ? "{$prefix}\n" . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $prefix;

            ForwardMessageToTelegram::dispatch(
                $community->telegram_bot_token,
                $community->telegram_chat_id,
                $caption,
                $mediaUrl,
                $mediaType,
            );
        }

        $message->load('user:id,name,username,avatar');

        try {
            ChatMessageSent::dispatch($community->id, [
                'id'              => $message->id,
                'content'         => $message->content,
                'created_at'      => $message->created_at,
                'telegram_author' => null,
                'media_url'       => $message->media_url,
                'media_type'      => $message->media_type,
                'user'            => [
                    'id'       => $message->user->id,
                    'name'     => $message->user->name,
                    'username' => $message->user->username,
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Chat broadcast failed', ['error' => $e->getMessage()]);
        }

        return $message;
    }
}
