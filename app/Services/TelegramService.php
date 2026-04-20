<?php

namespace App\Services;

use App\Contracts\TelegramGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TelegramService implements TelegramGateway
{
    private const API_BASE = 'https://api.telegram.org/bot';

    public function sendMessage(string $token, string $chatId, string $text): void
    {
        try {
            Http::timeout(5)->post(self::API_BASE.$token.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage failed', ['error' => $e->getMessage()]);
        }
    }

    public function setWebhook(string $token, string $url, string $secret): bool
    {
        try {
            $response = Http::timeout(10)->post(self::API_BASE.$token.'/setWebhook', [
                'url' => $url,
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
            Http::timeout(5)->post(self::API_BASE.$token.'/deleteWebhook');
        } catch (\Throwable $e) {
            Log::warning('Telegram deleteWebhook failed', ['error' => $e->getMessage()]);
        }
    }

    public function sendPhoto(string $token, string $chatId, string $photoUrl, ?string $caption = null): void
    {
        try {
            Http::timeout(15)->post(self::API_BASE.$token.'/sendPhoto', array_filter([
                'chat_id' => $chatId,
                'photo' => $photoUrl,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Telegram sendPhoto failed', ['error' => $e->getMessage()]);
        }
    }

    public function sendVideo(string $token, string $chatId, string $videoUrl, ?string $caption = null): void
    {
        try {
            Http::timeout(15)->post(self::API_BASE.$token.'/sendVideo', array_filter([
                'chat_id' => $chatId,
                'video' => $videoUrl,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Telegram sendVideo failed', ['error' => $e->getMessage()]);
        }
    }

    public function mirrorFile(string $token, string $fileId, string $folder): ?string
    {
        try {
            $response = Http::timeout(10)->get(self::API_BASE.$token.'/getFile', [
                'file_id' => $fileId,
            ]);

            $filePath = $response->json('result.file_path');
            if (! $filePath) {
                return null;
            }

            $download = Http::timeout(30)->get('https://api.telegram.org/file/bot'.$token.'/'.$filePath);
            if (! $download->successful()) {
                return null;
            }

            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'bin';
            $storedPath = trim($folder, '/').'/'.Str::uuid().'.'.$extension;
            Storage::disk(config('filesystems.default'))->put($storedPath, $download->body());

            return Storage::url($storedPath);
        } catch (\Throwable $e) {
            Log::warning('Telegram mirrorFile failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getChatMemberCount(string $token, string $chatId): ?int
    {
        try {
            $response = Http::timeout(5)->get(self::API_BASE.$token.'/getChatMemberCount', [
                'chat_id' => $chatId,
            ]);

            return $response->json('result');
        } catch (\Throwable $e) {
            Log::warning('Telegram getChatMemberCount failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function webhookSecret(string $token): string
    {
        return substr(hash('sha256', $token), 0, 32);
    }
}
