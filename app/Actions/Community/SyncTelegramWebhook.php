<?php

namespace App\Actions\Community;

use App\Models\Community;
use App\Services\TelegramService;

class SyncTelegramWebhook
{
    public function __construct(private TelegramService $telegram) {}

    public function execute(Community $community, ?string $oldToken): void
    {
        $newToken = $community->telegram_bot_token;

        if ($newToken && $community->telegram_chat_id && $newToken !== $oldToken) {
            $url = route('webhooks.telegram', ['slug' => $community->slug]);
            $this->telegram->setWebhook($newToken, $url, $this->telegram->webhookSecret($newToken));
            return;
        }

        if (! $newToken && $oldToken) {
            $this->telegram->deleteWebhook($oldToken);
        }
    }
}
