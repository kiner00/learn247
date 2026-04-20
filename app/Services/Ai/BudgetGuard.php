<?php

namespace App\Services\Ai;

use App\Exceptions\AiBudgetExceededException;
use App\Models\AiUsageLog;
use Carbon\CarbonInterface;

class BudgetGuard
{
    /**
     * Throw AiBudgetExceededException if the user or community has exceeded
     * their hard-cap spend within the configured window. No-op if hard caps
     * are disabled or no IDs are supplied.
     */
    public static function assertAllowed(?int $userId, ?int $communityId): void
    {
        if (! config('ai_budgets.hard_caps.enabled')) {
            return;
        }

        $window = (int) config('ai_budgets.hard_caps.window_minutes', 60);
        $since = now()->subMinutes($window);

        if ($userId) {
            $userCap = (float) config('ai_budgets.hard_caps.max_usd_per_user', 0);
            if ($userCap > 0) {
                $spent = static::spentSince(userId: $userId, since: $since);
                if ($spent >= $userCap) {
                    throw new AiBudgetExceededException('user', $userId, $spent, $userCap, $window);
                }
            }
        }

        if ($communityId) {
            $communityCap = (float) config('ai_budgets.hard_caps.max_usd_per_community', 0);
            if ($communityCap > 0) {
                $spent = static::spentSince(communityId: $communityId, since: $since);
                if ($spent >= $communityCap) {
                    throw new AiBudgetExceededException('community', $communityId, $spent, $communityCap, $window);
                }
            }
        }
    }

    public static function spentSince(
        CarbonInterface $since,
        ?int $userId = null,
        ?int $communityId = null,
    ): float {
        return (float) AiUsageLog::query()
            ->where('created_at', '>=', $since)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($communityId, fn ($q) => $q->where('community_id', $communityId))
            ->sum('cost_usd');
    }
}
