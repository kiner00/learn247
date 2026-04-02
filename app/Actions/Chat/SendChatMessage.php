<?php

namespace App\Actions\Chat;

use App\Events\ChatMessageSent;
use App\Models\Community;
use App\Models\Message;
use App\Models\User;
use App\Services\TelegramService;

class SendChatMessage
{
    public function __construct(private TelegramService $telegram) {}

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
            try {
                $isAdmin = $community->owner_id === $user->id;
                $prefix  = $isAdmin ? "From Curzzo Admin - <b>{$user->name}</b>" : "From Curzzo Member - <b>{$user->name}</b>";
                $caption = $content ? "{$prefix}\n" . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $prefix;

                if ($mediaUrl && $mediaType === 'image') {
                    $this->telegram->sendPhoto($community->telegram_bot_token, $community->telegram_chat_id, $mediaUrl, $caption);
                } elseif ($mediaUrl && $mediaType === 'video') {
                    $this->telegram->sendVideo($community->telegram_bot_token, $community->telegram_chat_id, $mediaUrl, $caption);
                } else {
                    $this->telegram->sendMessage(
                        $community->telegram_bot_token,
                        $community->telegram_chat_id,
                        "{$prefix}\n" . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Telegram send failed', ['error' => $e->getMessage()]);
            }
        }

        $message->load('user:id,name,username,avatar');

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

        return $message;
    }
}
