<?php

namespace App\Support;

use App\Models\Affiliate;
use Illuminate\Support\Str;

class AffiliateCodeGenerator
{
    public static function generate(): string
    {
        do {
            $code = Str::random(12);
        } while (Affiliate::where('code', $code)->exists());

        return $code;
    }
}
