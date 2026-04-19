<?php

namespace App\Services\Ai;

class CostCalculator
{
    // USD per 1M tokens. Update when provider pricing changes.
    private const TOKEN_PRICING = [
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
        'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00],
        'gemini-2.0-flash' => ['input' => 0.10, 'output' => 0.40],
        'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
        'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
    ];

    private const IMAGE_PRICE_USD = 0.04;

    public static function agentCost(?string $model, int $promptTokens, int $completionTokens): float
    {
        if (! $model) {
            return 0.0;
        }

        $pricing = self::TOKEN_PRICING[strtolower($model)] ?? null;
        if (! $pricing) {
            return 0.0;
        }

        return ($promptTokens * $pricing['input'] + $completionTokens * $pricing['output']) / 1_000_000;
    }

    public static function imageCost(int $imageCount = 1): float
    {
        return self::IMAGE_PRICE_USD * max(1, $imageCount);
    }
}
