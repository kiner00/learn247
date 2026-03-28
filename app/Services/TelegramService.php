<?php

namespace App\Services;

use App\Contracts\TelegramGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService implements TelegramGateway
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

    public function sendPhoto(string $token, string $chatId, string $photoUrl, ?string $caption = null): void
    {
        try {
            Http::timeout(15)->post(self::API_BASE . $token . '/sendPhoto', array_filter([
                'chat_id'    => $chatId,
                'photo'      => $photoUrl,
                'caption'    => $caption,
                'parse_mode' => 'HTML',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Telegram sendPhoto failed', ['error' => $e->getMessage()]);
        }
    }

    public function sendVideo(string $token, string $chatId, string $videoUrl, ?string $caption = null): void
    {
        try {
            Http::timeout(15)->post(self::API_BASE . $token . '/sendVideo', array_filter([
                'chat_id'    => $chatId,
                'video'      => $videoUrl,
                'caption'    => $caption,
                'parse_mode' => 'HTML',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Telegram sendVideo failed', ['error' => $e->getMessage()]);
        }
    }

    public function getFileUrl(string $token, string $fileId): ?string
    {
        try {
            $response = Http::timeout(10)->get(self::API_BASE . $token . '/getFile', [
                'file_id' => $fileId,
            ]);

            $filePath = $response->json('result.file_path');
            if (! $filePath) {
                return null;
            }

            return 'https://api.telegram.org/file/bot' . $token . '/' . $filePath;
        } catch (\Throwable $e) {
            Log::warning('Telegram getFile failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function webhookSecret(string $token): string
    {
        return substr(hash('sha256', $token), 0, 32);
    }
}
