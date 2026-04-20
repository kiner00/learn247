<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Budget Alerts (observability)
    |--------------------------------------------------------------------------
    |
    | When a user or community exceeds `threshold_usd` of spend within
    | `window_minutes`, send an email to `to`. Alerts are deduped per
    | (scope, id) for `cooldown_minutes` to prevent spam.
    |
    */
    'alerts' => [
        'to' => env('AI_ALERT_EMAIL'),
        'threshold_usd' => (float) env('AI_ALERT_THRESHOLD_USD', 5.00),
        'window_minutes' => (int) env('AI_ALERT_WINDOW_MINUTES', 60),
        'cooldown_minutes' => (int) env('AI_ALERT_COOLDOWN_MINUTES', 360),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Hard Caps (enforcement)
    |--------------------------------------------------------------------------
    |
    | When enabled, expensive AI jobs (image generation) will throw
    | AiBudgetExceededException if the user OR community has spent more
    | than the cap in the last `window_minutes`. Defaults are conservative.
    |
    */
    'hard_caps' => [
        'enabled' => filter_var(env('AI_HARD_CAP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'max_usd_per_user' => (float) env('AI_HARD_CAP_USER_USD', 20.00),
        'max_usd_per_community' => (float) env('AI_HARD_CAP_COMMUNITY_USD', 50.00),
        'window_minutes' => (int) env('AI_HARD_CAP_WINDOW_MINUTES', 60),
    ],

];
