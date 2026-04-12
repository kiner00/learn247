<?php

namespace Tests\Unit\Support;

use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheKeysTest extends TestCase
{
    public function test_flush_user_membership_forgets_cache_key(): void
    {
        $userId = 42;
        $key = "user:{$userId}:community_ids";

        Cache::put($key, [1, 2, 3], 300);
        $this->assertNotNull(Cache::get($key));

        CacheKeys::flushUserMembership($userId);

        $this->assertNull(Cache::get($key));
    }

    public function test_flush_payment_delegates_to_community_creator_and_admin(): void
    {
        $communityId = 10;
        $ownerId = 5;

        // Seed keys that flushPayment should clear
        Cache::put(CacheKeys::leaderboard($communityId), 'data', 300);
        Cache::put(CacheKeys::communityAnalytics($communityId), 'data', 300);
        Cache::put(CacheKeys::creatorAnalytics($ownerId), 'data', 300);
        Cache::put(CacheKeys::creatorDashboard($ownerId), 'data', 300);
        Cache::put(CacheKeys::adminDashboard(), 'data', 300);
        Cache::put(CacheKeys::adminPayouts(), 'data', 300);

        CacheKeys::flushPayment($communityId, $ownerId);

        $this->assertNull(Cache::get(CacheKeys::leaderboard($communityId)));
        $this->assertNull(Cache::get(CacheKeys::communityAnalytics($communityId)));
        $this->assertNull(Cache::get(CacheKeys::creatorAnalytics($ownerId)));
        $this->assertNull(Cache::get(CacheKeys::creatorDashboard($ownerId)));
        $this->assertNull(Cache::get(CacheKeys::adminDashboard()));
        $this->assertNull(Cache::get(CacheKeys::adminPayouts()));
    }

    public function test_key_format_methods_return_expected_strings(): void
    {
        $this->assertSame('leaderboard:1', CacheKeys::leaderboard(1));
        $this->assertSame('leaderboard_dist:2', CacheKeys::leaderboardDistribution(2));
        $this->assertSame('leaderboard_top:3:10', CacheKeys::leaderboardTopMembers(3, 10));
        $this->assertSame('community_analytics:4', CacheKeys::communityAnalytics(4));
        $this->assertSame('creator_analytics:5', CacheKeys::creatorAnalytics(5));
        $this->assertSame('creator_dashboard:6', CacheKeys::creatorDashboard(6));
        $this->assertSame('admin_dashboard', CacheKeys::adminDashboard());
        $this->assertSame('admin_payouts', CacheKeys::adminPayouts());
        $this->assertSame('affiliate_dashboard:7', CacheKeys::affiliateDashboard(7));
    }

    public function test_admin_creator_analytics_key_uses_md5(): void
    {
        $key = CacheKeys::adminCreatorAnalytics('search', 'pro');
        $expected = 'admin_creator_analytics:' . md5('search:pro');
        $this->assertSame($expected, $key);
    }

    public function test_affiliate_chart_key_uses_md5(): void
    {
        $key = CacheKeys::affiliateChart('1,2,3', '30d');
        $expected = 'affiliate_chart:' . md5('1,2,3:30d');
        $this->assertSame($expected, $key);
    }
}
