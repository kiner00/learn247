<?php

namespace Tests\Feature\Services;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Analytics\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminDashboardService;
    }

    // ── Structure ─────────────────────────────────────────────────────────────

    public function test_returns_all_expected_keys(): void
    {
        $result = $this->service->build();

        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('revenue', $result);
        $this->assertArrayHasKey('byCategory', $result);
        $this->assertArrayHasKey('recentCommunities', $result);
        $this->assertArrayHasKey('recentUsers', $result);
        $this->assertArrayHasKey('pendingOnboarding', $result);

        $this->assertArrayHasKey('total_users', $result['stats']);
        $this->assertArrayHasKey('total_communities', $result['stats']);
        $this->assertArrayHasKey('total_members', $result['stats']);
        $this->assertArrayHasKey('active_subscriptions', $result['stats']);
        $this->assertArrayHasKey('monthly_revenue', $result['stats']);

        $this->assertArrayHasKey('gross', $result['revenue']);
        $this->assertArrayHasKey('platform_fee', $result['revenue']);
        $this->assertArrayHasKey('creator_net', $result['revenue']);
        $this->assertArrayHasKey('affiliate_commission_total', $result['revenue']);
        $this->assertArrayHasKey('affiliate_commission_paid', $result['revenue']);
        $this->assertArrayHasKey('affiliate_commission_pending', $result['revenue']);
    }

    public function test_returns_zeros_on_empty_database(): void
    {
        $result = $this->service->build();

        $this->assertSame(0, $result['stats']['total_users']);
        $this->assertSame(0, $result['stats']['total_communities']);
        $this->assertSame(0, $result['stats']['total_members']);
        $this->assertSame(0, $result['stats']['active_subscriptions']);
        $this->assertEquals(0, $result['revenue']['gross']);
        $this->assertEquals(0, $result['revenue']['affiliate_commission_total']);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function test_counts_users_communities_and_members(): void
    {
        User::factory()->count(3)->create();
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::create(['user_id' => User::factory()->create()->id, 'community_id' => $community->id]);
        CommunityMember::create(['user_id' => User::factory()->create()->id, 'community_id' => $community->id]);

        $result = $this->service->build();

        $this->assertSame(6, $result['stats']['total_users']); // 3 + owner + 2 members
        $this->assertSame(1, $result['stats']['total_communities']);
        $this->assertSame(2, $result['stats']['total_members']);
    }

    public function test_counts_active_subscriptions_with_paid_payments(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        $sub = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $member->id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        $result = $this->service->build();

        $this->assertSame(1, $result['stats']['active_subscriptions']);
    }

    public function test_does_not_count_active_subscriptions_without_paid_payments(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);
        // No payment created

        $result = $this->service->build();

        $this->assertSame(0, $result['stats']['active_subscriptions']);
    }

    // ── Revenue ───────────────────────────────────────────────────────────────

    public function test_gross_revenue_sums_paid_payments(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $sub = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Payment::create(['subscription_id' => $sub->id, 'community_id' => $community->id, 'user_id' => $member->id,
            'amount' => 1000, 'currency' => 'PHP', 'status' => Payment::STATUS_PAID, 'metadata' => [], 'paid_at' => now()]);

        Payment::create(['subscription_id' => $sub->id, 'community_id' => $community->id, 'user_id' => $member->id,
            'amount' => 500, 'currency' => 'PHP', 'status' => Payment::STATUS_PENDING, 'metadata' => []]);

        $result = $this->service->build();

        $this->assertEquals(1000.0, $result['revenue']['gross']);
    }

    public function test_affiliate_commission_total_is_sum_across_all_conversions(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $affUser = User::factory()->create();
        $affiliate = Affiliate::create(['community_id' => $community->id, 'user_id' => $affUser->id, 'code' => 'AFF', 'status' => Affiliate::STATUS_ACTIVE]);
        $member = User::factory()->create();
        $sub = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        AffiliateConversion::create(['affiliate_id' => $affiliate->id, 'subscription_id' => $sub->id, 'referred_user_id' => $member->id,
            'sale_amount' => 1000, 'platform_fee' => 98, 'commission_amount' => 100, 'creator_amount' => 802]);

        AffiliateConversion::create(['affiliate_id' => $affiliate->id, 'subscription_id' => $sub->id, 'referred_user_id' => $member->id,
            'sale_amount' => 500, 'platform_fee' => 49, 'commission_amount' => 50, 'creator_amount' => 401]);

        $result = $this->service->build();

        $this->assertEquals(150.0, $result['revenue']['affiliate_commission_total']);
    }

    public function test_affiliate_pending_commission_excludes_paid_ones(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $affUser = User::factory()->create();
        $affiliate = Affiliate::create(['community_id' => $community->id, 'user_id' => $affUser->id, 'code' => 'AFF2', 'status' => Affiliate::STATUS_ACTIVE]);
        $member = User::factory()->create();
        $sub = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        AffiliateConversion::create(['affiliate_id' => $affiliate->id, 'subscription_id' => $sub->id, 'referred_user_id' => $member->id,
            'sale_amount' => 1000, 'platform_fee' => 98, 'commission_amount' => 200, 'creator_amount' => 702,
            'status' => AffiliateConversion::STATUS_PAID]);

        AffiliateConversion::create(['affiliate_id' => $affiliate->id, 'subscription_id' => $sub->id, 'referred_user_id' => $member->id,
            'sale_amount' => 500, 'platform_fee' => 49, 'commission_amount' => 80, 'creator_amount' => 371]);

        $result = $this->service->build();

        $this->assertEquals(200.0, $result['revenue']['affiliate_commission_paid']);
        $this->assertEquals(80.0, $result['revenue']['affiliate_commission_pending']);
    }

    public function test_creator_net_equals_gross_minus_fees_and_commissions(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $sub = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Payment::create(['subscription_id' => $sub->id, 'community_id' => $community->id, 'user_id' => $member->id,
            'amount' => 1000, 'currency' => 'PHP', 'status' => Payment::STATUS_PAID, 'metadata' => [], 'paid_at' => now()]);

        $result = $this->service->build();
        $revenue = $result['revenue'];

        $this->assertEquals(
            round($revenue['gross'] - $revenue['platform_fee'] - $revenue['affiliate_commission_total'], 2),
            $revenue['creator_net']
        );
    }

    // ── Recent activity ───────────────────────────────────────────────────────

    public function test_recent_communities_returns_at_most_five(): void
    {
        $owner = User::factory()->create();
        Community::factory()->count(7)->create(['owner_id' => $owner->id]);

        $result = $this->service->build();

        $this->assertCount(5, $result['recentCommunities']);
    }

    public function test_recent_users_returns_at_most_five(): void
    {
        User::factory()->count(8)->create();

        $result = $this->service->build();

        $this->assertCount(5, $result['recentUsers']);
    }

    public function test_pending_onboarding_only_includes_needs_password_setup_users(): void
    {
        User::factory()->create(['needs_password_setup' => true]);
        User::factory()->create(['needs_password_setup' => true]);
        User::factory()->create(['needs_password_setup' => false]);

        $result = $this->service->build();

        $this->assertSame(2, $result['pendingOnboarding']->total());
    }

    public function test_by_category_groups_communities(): void
    {
        $owner = User::factory()->create();
        Community::factory()->create(['owner_id' => $owner->id, 'category' => 'Tech']);
        Community::factory()->create(['owner_id' => $owner->id, 'category' => 'Tech']);
        Community::factory()->create(['owner_id' => $owner->id, 'category' => 'Business']);

        $result = $this->service->build();

        $categories = collect($result['byCategory'])->pluck('total', 'category');
        $this->assertEquals(2, $categories['Tech']);
        $this->assertEquals(1, $categories['Business']);
    }

    public function test_null_category_shown_as_uncategorized(): void
    {
        $owner = User::factory()->create();
        Community::factory()->create(['owner_id' => $owner->id, 'category' => null]);

        $result = $this->service->build();

        $categories = collect($result['byCategory'])->pluck('total', 'category');
        $this->assertEquals(1, $categories['Uncategorized']);
    }
}
