<?php

namespace App\Contracts;

interface WebhookHandler
{
    public function matches(string $xenditId): bool;

    public function handle(array $payload, string $eventId, string $status): void;
}
