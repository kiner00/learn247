<?php

namespace App\Support;

class PayoutChannelMap
{
    private const MAP = [
        'gcash' => 'PH_GCASH',
        'maya' => 'PH_PAYMAYA',
    ];

    public static function resolve(string $method): string
    {
        return self::MAP[$method] ?? throw new \InvalidArgumentException("Unsupported payout method: {$method}");
    }

    public static function supports(?string $method): bool
    {
        return $method !== null && isset(self::MAP[$method]);
    }

    public static function supportedMethods(): array
    {
        return array_keys(self::MAP);
    }
}
