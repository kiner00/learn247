<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ForwardMessageToTelegram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private string $token,
        private string $chatId,
        private string $caption,
        private ?string $mediaUrl = null,
        private ?string $mediaType = null,
    ) {}

    public function handle(TelegramService $telegram): void
    {
        if ($this->mediaUrl && $this->mediaType === 'image') {
            $telegram->sendPhoto($this->token, $this->chatId, $this->mediaUrl, $this->caption);
        } elseif ($this->mediaUrl && $this->mediaType === 'video') {
            $telegram->sendVideo($this->token, $this->chatId, $this->mediaUrl, $this->caption);
        } else {
            $telegram->sendMessage($this->token, $this->chatId, $this->caption);
        }
    }
}
