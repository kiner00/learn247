<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class CacheKeys
{
    // TTLs in seconds
    public const TTL_LEADERBOARD = 300;        // 5 min

    public const TTL_ANALYTICS = 600;           // 10 min

    public const TTL_ADMIN_DASHBOARD = 900;     // 15 min

    // Key prefixes
    public static function leaderboard(int $communityId): string
    {
        return "leaderboard:{$communityId}";
    }

    public static function leaderboardDistribution(int $communityId): string
    {
        return "leaderboard_dist:{$communityId}";
    }

    public static function leaderboardTopMembers(int $communityId, int $limit): string
    {
        return "leaderboard_top:{$communityId}:{$limit}";
    }

    public static function communityAnalytics(int $communityId): string
    {
        return "community_analytics:{$communityId}";
    }

    public static function creatorAnalytics(int $userId): string
    {
        return "creator_analytics:{$userId}";
    }

    public static function creatorDashboard(int $userId): string
    {
        return "creator_dashboard:{$userId}";
    }

    public static function adminDashboard(): string
    {
        return 'admin_dashboard';
    }

    public static function adminPayouts(): string
    {
        return 'admin_payouts';
    }

    public static function adminCreatorAnalytics(string $search, string $plan): string
    {
        return 'admin_creator_analytics:'.md5("{$search}:{$plan}");
    }

    public static function affiliateDashboard(int $communityId): string
    {
        return "affiliate_dashboard:{$communityId}";
    }

    public static function affiliateChart(string $ids, string $period): string
    {
        return 'affiliate_chart:'.md5("{$ids}:{$period}");
    }

    // ── Invalidation helpers ──────────────────────────────────

    public static function flushCommunity(int $communityId): void
    {
        Cache::forget(self::leaderboard($communityId));
        Cache::forget(self::leaderboardDistribution($communityId));
        Cache::forget(self::communityAnalytics($communityId));
        Cache::forget(self::affiliateDashboard($communityId));

        // Flush all topMembers variants (common limits: 3, 5, 10)
        foreach ([3, 5, 10] as $limit) {
            Cache::forget(self::leaderboardTopMembers($communityId, $limit));
        }
    }

    public static function flushLeaderboard(int $communityId): void
    {
        Cache::forget(self::leaderboard($communityId));
        Cache::forget(self::leaderboardDistribution($communityId));
        foreach ([3, 5, 10] as $limit) {
            Cache::forget(self::leaderboardTopMembers($communityId, $limit));
        }
    }

    public static function flushCreator(int $userId): void
    {
        Cache::forget(self::creatorAnalytics($userId));
        Cache::forget(self::creatorDashboard($userId));
    }

    public static function flushAdmin(): void
    {
        Cache::forget(self::adminDashboard());
        Cache::forget(self::adminPayouts());
    }

    public static function flushPayment(int $communityId, int $ownerId): void
    {
        self::flushCommunity($communityId);
        self::flushCreator($ownerId);
        self::flushAdmin();
    }

    public static function flushUserMembership(int $userId): void
    {
        Cache::forget("user:{$userId}:community_ids");
    }
}
