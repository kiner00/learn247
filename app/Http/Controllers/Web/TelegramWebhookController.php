<?php

namespace App\Http\Controllers\Web;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Message;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramWebhookController extends Controller
{
    public function __construct(private TelegramService $telegram) {}

    public function __invoke(Request $request, string $slug): Response
    {
        $community = Community::where('slug', $slug)->first();

        if (! $community || ! $community->telegram_bot_token || ! $community->telegram_chat_id) {
            return response('', 200);
        }

        // Verify secret token header
        $expectedSecret = $this->telegram->webhookSecret($community->telegram_bot_token);
        $providedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (! hash_equals($expectedSecret, $providedSecret)) {
            return response('', 200);
        }

        $update = $request->json()->all();

        $chatMessage = $update['message'] ?? $update['channel_post'] ?? null;
        if (! $chatMessage) {
            return response('', 200);
        }

        // Only accept messages from the configured chat
        $chatId = (string) ($chatMessage['chat']['id'] ?? '');
        if ($chatId !== $community->telegram_chat_id) {
            return response('', 200);
        }

        // Skip bot's own messages to avoid loops
        $fromUser = $chatMessage['from'] ?? null;
        if ($fromUser && ($fromUser['is_bot'] ?? false)) {
            return response('', 200);
        }

        $text      = $chatMessage['text'] ?? $chatMessage['caption'] ?? null;
        $mediaUrl  = null;
        $mediaType = null;

        if (isset($chatMessage['photo'])) {
            // Telegram sends multiple sizes; pick the largest
            $photo    = end($chatMessage['photo']);
            $fileId   = $photo['file_id'];
            $mediaUrl = $this->telegram->getFileUrl($community->telegram_bot_token, $fileId);
            $mediaType = 'image';
        } elseif (isset($chatMessage['video'])) {
            $fileId   = $chatMessage['video']['file_id'];
            $mediaUrl = $this->telegram->getFileUrl($community->telegram_bot_token, $fileId);
            $mediaType = 'video';
        }

        // Skip if no text and no media
        if (! $text && ! $mediaUrl) {
            return response('', 200);
        }

        $authorName = trim(($fromUser['first_name'] ?? '') . ' ' . ($fromUser['last_name'] ?? ''));
        if (empty($authorName)) {
            $authorName = $fromUser['username'] ?? 'Telegram';
        }

        $message = Message::create([
            'community_id'    => $community->id,
            'user_id'         => $community->owner_id,
            'content'         => $text ?? '',
            'telegram_author' => $authorName,
            'media_url'       => $mediaUrl,
            'media_type'      => $mediaType,
        ]);

        ChatMessageSent::dispatch($community->id, [
            'id'              => $message->id,
            'content'         => $message->content,
            'created_at'      => $message->created_at,
            'telegram_author' => $message->telegram_author,
            'media_url'       => $message->media_url,
            'media_type'      => $message->media_type,
            'user'            => null,
        ]);

        return response('', 200);
    }
}
