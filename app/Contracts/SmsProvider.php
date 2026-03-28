<?php

namespace App\Contracts;

use App\Models\Community;

interface SmsProvider
{
    /**
     * @param  array  $numbers  E.164 format
     * @return array{sent: int, failed: int, errors: array}
     */
    public function blast(Community $community, array $numbers, string $message): array;
}
