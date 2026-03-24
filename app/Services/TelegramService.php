<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private const API_BASE = 'https://api.telegram.org/bot';

    public function sendMessage(string $token, string $chatId, string $text): void
    {
        try {
            Http::timeout(5)->post(self::API_BASE . $token . '/sendMessage', [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage failed', ['error' => $e->getMessage()]);
        }
    }

    public function setWebhook(string $token, string $url, string $secret): bool
    {
        try {
            $response = Http::timeout(10)->post(self::API_BASE . $token . '/setWebhook', [
                'url'          => $url,
                'secret_token' => $secret,
                'allowed_updates' => ['message'],
            ]);

            return $response->json('ok', false);
        } catch (\Throwable $e) {
            Log::warning('Telegram setWebhook failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function deleteWebhook(string $token): void
    {
        try {
            Http::timeout(5)->post(self::API_BASE . $token . '/deleteWebhook');
        } catch (\Throwable $e) {
            Log::warning('Telegram deleteWebhook failed', ['error' => $e->getMessage()]);
        }
    }

    public function webhookSecret(string $token): string
    {
        return substr(hash('sha256', $token), 0, 32);
    }
}
