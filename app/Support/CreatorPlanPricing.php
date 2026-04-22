<?php

namespace App\Support;

use App\Models\CreatorSubscription;
use App\Models\Setting;

class CreatorPlanPricing
{
    private const DEFAULTS = [
        CreatorSubscription::PLAN_BASIC => [
            CreatorSubscription::CYCLE_MONTHLY => 499,
            CreatorSubscription::CYCLE_ANNUAL => 4990,
        ],
        CreatorSubscription::PLAN_PRO => [
            CreatorSubscription::CYCLE_MONTHLY => 1999,
            CreatorSubscription::CYCLE_ANNUAL => 19990,
        ],
    ];

    public static function settingKey(string $plan, string $cycle): string
    {
        $base = $plan === CreatorSubscription::PLAN_PRO ? 'creator_plan_pro' : 'creator_plan_basic';

        return $cycle === CreatorSubscription::CYCLE_ANNUAL
            ? "{$base}_annual_price"
            : "{$base}_price";
    }

    public static function defaultPrice(string $plan, string $cycle): float
    {
        return (float) (self::DEFAULTS[$plan][$cycle] ?? 0);
    }

    public static function priceFor(string $plan, string $cycle): float
    {
        return (float) Setting::get(self::settingKey($plan, $cycle), self::defaultPrice($plan, $cycle));
    }
}
