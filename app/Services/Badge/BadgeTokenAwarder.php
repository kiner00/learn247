<?php

namespace App\Services\Badge;

use App\Models\Badge;
use App\Models\CrzTokenTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BadgeTokenAwarder
{
    private const TOKEN_REWARDS = [
        'early_bird'    => 1,
        'early_builder' => 10,
    ];

    public function award(User $user, Badge $badge): void
    {
        $amount = self::TOKEN_REWARDS[$badge->key] ?? 0;
        if ($amount <= 0) return;

        DB::transaction(function () use ($user, $badge, $amount) {
            CrzTokenTransaction::create([
                'user_id'   => $user->id,
                'amount'    => $amount,
                'type'      => 'award',
                'reason'    => $badge->key . '_badge',
                'reference' => $badge->key,
            ]);

            $user->increment('crz_token_balance', $amount);
        });
    }
}
