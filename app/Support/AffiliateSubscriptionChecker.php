<?php

namespace App\Support;

use App\Models\Subscription;

class AffiliateSubscriptionChecker
{
    public static function isActivelySubscribed(int $userId, int $communityId): bool
    {
        return Subscription::where('user_id', $userId)
            ->where('community_id', $communityId)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }
}
