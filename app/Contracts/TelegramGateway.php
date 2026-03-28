<?php

namespace App\Contracts;

interface TelegramGateway
{
    public function sendMessage(string $token, string $chatId, string $text): void;

    public function setWebhook(string $token, string $url, string $secret): bool;

    public function deleteWebhook(string $token): void;

    public function sendPhoto(string $token, string $chatId, string $photoUrl, ?string $caption = null): void;

    public function sendVideo(string $token, string $chatId, string $videoUrl, ?string $caption = null): void;

    public function getFileUrl(string $token, string $fileId): ?string;

    public function webhookSecret(string $token): string;
}
