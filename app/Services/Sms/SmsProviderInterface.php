<?php

namespace App\Services\Sms;

use App\Models\Community;

interface SmsProviderInterface
{
    /**
     * @param  array  $numbers  E.164 format
     * @return array{sent: int, failed: int, errors: array}
     */
    public function send(Community $community, array $numbers, string $message): array;
}
