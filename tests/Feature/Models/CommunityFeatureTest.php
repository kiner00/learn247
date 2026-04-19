<?php

namespace Tests\Feature\Models;

use App\Models\Community;
use App\Models\CreatorSubscription;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscribers_count_returns_correct_count(): void
    {
        $community = Community::factory()->create();

        // Active and not expired
        Subscription::factory()->create([
            'community_id' => $community->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDays(30),
        ]);

        // Active but expired
        Subscription::factory()->create([
            'community_id' => $community->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        // Inactive
        Subscription::factory()->create([
            'community_id' => $community->id,
            'status' => Subscription::STATUS_CANCELLED,
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertSame(1, $community->activeSubscribersCount());
    }

    public function test_platform_fee_rate_returns_default_when_no_plan(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->assertSame(0.098, $community->platformFeeRate());
    }

    public function test_platform_fee_rate_returns_basic_rate(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id' => $owner->id,
            'plan' => 'basic',
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_id' => 'test',
        ]);

        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->assertSame(0.049, $community->platformFeeRate());
    }

    public function test_platform_fee_rate_returns_pro_rate(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id' => $owner->id,
            'plan' => 'pro',
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_id' => 'test',
        ]);

        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->assertSame(0.029, $community->platformFeeRate());
    }

    // ─── url ──────────────────────────────────────────────────────────────

    public function test_url_returns_custom_domain_when_set(): void
    {
        $community = new Community;
        $community->custom_domain = 'my.community.com';

        $this->assertSame('https://my.community.com', $community->url());
    }

    public function test_url_returns_subdomain_url_when_set(): void
    {
        $community = new Community;
        $community->custom_domain = null;
        $community->subdomain = 'test';
        $community->slug = 'test-community';

        config(['app.url' => 'https://curzzo.com']);

        $this->assertSame('https://test.curzzo.com', $community->url());
    }

    public function test_url_returns_slug_url_as_fallback(): void
    {
        $community = new Community;
        $community->custom_domain = null;
        $community->subdomain = null;
        $community->slug = 'my-community';

        config(['app.url' => 'https://curzzo.com']);

        $this->assertSame('https://curzzo.com/communities/my-community', $community->url());
    }

    // ─── isPendingDeletion ────────────────────────────────────────────────

    public function test_is_pending_deletion_returns_true_when_date_is_set(): void
    {
        $community = new Community;
        $community->deletion_requested_at = now();

        $this->assertTrue($community->isPendingDeletion());
    }

    public function test_is_pending_deletion_returns_false_when_date_is_null(): void
    {
        $community = new Community;
        $community->deletion_requested_at = null;

        $this->assertFalse($community->isPendingDeletion());
    }
}
