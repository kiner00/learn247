<?php

use Laravel\Ai\Enums\Lab;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Model Tier
    |--------------------------------------------------------------------------
    */

    'default_tier' => 'basic',

    /*
    |--------------------------------------------------------------------------
    | Available Model Tiers
    |--------------------------------------------------------------------------
    |
    | Each tier maps to a provider + model combination. To add a new tier,
    | just add an entry here — no other code changes needed.
    |
    */

    'tiers' => [
        'basic' => [
            'provider' => Lab::Gemini,
            'model' => 'gemini-2.5-flash',
            'label' => 'Curzzos Basic',
            'description' => 'Fast responses, great for most use cases',
        ],
        'pro' => [
            'provider' => Lab::Gemini,
            'model' => 'gemini-2.5-pro',
            'label' => 'Curzzos Pro',
            'description' => 'Advanced reasoning, deeper analysis',
        ],
    ],

];
