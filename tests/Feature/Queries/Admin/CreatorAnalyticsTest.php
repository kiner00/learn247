<?php

namespace Tests\Feature\Queries\Admin;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Admin\CreatorAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private CreatorAnalytics $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new CreatorAnalytics();
    }

    // ─── helpers ───────────────────────────────────────────────────────────────

    private function createCommunityWithOwner(string $ownerName = 'Creator', float $price = 499.00): array
    {
        $owner = User::factory()->create(['name' => $ownerName]);
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price'    => $price,
        ]);

        return [$owner, $community];
    }

    private function createPaidPayment(Community $community, float $amount, float $processingFee = 0, float $platformFee = 0): Payment
    {
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);

        return Payment::create([
            'subscription_id'    => $sub->id,
            'community_id'       => $community->id,
            'user_id'            => $sub->user_id,
            'amount'             => $amount,
            'processing_fee'     => $processingFee,
            'platform_fee'       => $platformFee,
            'currency'           => 'PHP',
            'status'             => Payment::STATUS_PAID,
            'metadata'           => [],
            'paid_at'            => now(),
        ]);
    }

    private function createAffiliateConversion(Community $community, float $commission): void
    {
        $affUser = User::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $affUser->id,
            'code'         => strtoupper(fake()->unique()->lexify('????????')),
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);

        AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $sub->id,
            'referred_user_id'  => $sub->user_id,
            'sale_amount'       => 500,
            'platform_fee'      => 75,
            'commission_amount' => $commission,
            'creator_amount'    => 500 - 75 - $commission,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
    }

    private function createOwnerPayout(Community $community, User $owner, float $amount, string $status = 'paid'): void
    {
        OwnerPayout::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'amount'       => $amount,
            'status'       => $status,
        ]);
    }

    // ─── structure ─────────────────────────────────────────────────────────────

    public function test_returns_correct_top_level_structure(): void
    {
        $result = $this->query->execute();

        $this->assertArrayHasKey('creators', $result);
        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertIsArray($result['creators']);
        $this->assertIsArray($result['totals']);
    }

    public function test_filters_are_returned(): void
    {
        $result = $this->query->execute('test-search', 'pro');

        $this->assertEquals('test-search', $result['filters']['search']);
        $this->assertEquals('pro', $result['filters']['plan']);
    }

    // ─── empty state ───────────────────────────────────────────────────────────

    public function test_empty_totals_when_no_communities(): void
    {
        $result = $this->query->execute();

        $this->assertEmpty($result['creators']);
        $this->assertEquals(0, $result['totals']['gross']);
        $this->assertEquals(0, $result['totals']['processing_fee']);
        $this->assertEquals(0, $result['totals']['platform_fee']);
        $this->assertEquals(0, $result['totals']['net_platform_profit']);
        $this->assertEquals(0, $result['totals']['affiliate_commission']);
        $this->assertEquals(0, $result['totals']['creator_earned']);
        $this->assertEquals(0, $result['totals']['creator_paid']);
        $this->assertEquals(0, $result['totals']['creator_pending']);
    }

    // ─── single community row calculations ─────────────────────────────────────

    public function test_row_calculations_with_payments(): void
    {
        [$owner, $community] = $this->createCommunityWithOwner('Alice', 499);

        // Payment: amount=1000, processingFee=50, platformFee=150
        $this->createPaidPayment($community, 1000, 50, 150);

        $result = $this->query->execute();

        $this->assertCount(1, $result['creators']);
        $row = $result['creators'][0];

        $this->assertEquals($community->id, $row['community_id']);
        $this->assertEquals($community->name, $row['community_name']);
        $this->assertEquals('Alice', $row['creator_name']);
        $this->assertEquals(1000.0, $row['gross']);
        $this->assertEquals(50.0, $row['processing_fee']);
        $this->assertEquals(150.0, $row['platform_fee']);
        // net_platform_profit = platformFee - processingFee = 150 - 50 = 100
        $this->assertEquals(100.0, $row['net_platform_profit']);
        $this->assertTrue($row['is_profitable']);
    }

    public function test_row_calculations_with_affiliate_commission(): void
    {
        [$owner, $community] = $this->createCommunityWithOwner();

        $this->createPaidPayment($community, 1000, 50, 150);
        $this->createAffiliateConversion($community, 80);

        $result = $this->query->execute();
        $row = $result['creators'][0];

        $this->assertEquals(80.0, $row['affiliate_commission']);
        // creator_earned = gross - platformFee - affComm = 1000 - 150 - 80 = 770
        $this->assertEquals(770.0, $row['creator_earned']);
    }

    public function test_row_calculations_with_owner_payouts(): void
    {
        [$owner, $community] = $this->createCommunityWithOwner();

        $this->createPaidPayment($community, 1000, 50, 150);
        $this->createOwnerPayout($community, $owner, 300);

        $result = $this->query->execute();
        $row = $result['creators'][0];

        // creator_earned = 1000 - 150 - 0 = 850
        $this->assertEquals(850.0, $row['creator_earned']);
        $this->assertEquals(300.0, $row['creator_paid']);
        // creator_pending = max(0, 850 - 300) = 550
        $this->assertEquals(550.0, $row['creator_pending']);
    }

    public function test_failed_owner_payouts_are_excluded(): void
    {
        [$owner, $community] = $this->createCommunityWithOwner();

        $this->createPaidPayment($community, 1000, 50, 150);
        $this->createOwnerPayout($community, $owner, 300, 'paid');
        $this->createOwnerPayout($community, $owner, 200, 'failed');

        $result = $this->query->execute();
        $row = $result['creators'][0];

        // Only the non-failed payout counts
        $this->assertEquals(300.0, $row['creator_paid']);
    }

    public function test_creator_pending_is_never_negative(): void
    {
        [$owner, $community] = $this->createCommunityWithOwner();

        $this->createPaidPayment($community, 100, 10, 50);
        // Overpay
        $this->createOwnerPayout($community, $owner, 9999);

        $result = $this->query->execute();
        $row = $result['creators'][0];

        $this->assertGreaterThanOrEqual(0, $row['creator_pending']);
    }

    public function test_is_profitable_false_when_processing_exceeds_platform_fee(): void
    {
        [$owner, $community] = $this->createCommunityWithOwner();

        // processingFee > platformFee => not profitable
        $this->createPaidPayment($community, 1000, 200, 50);

        $result = $this->query->execute();
        $row = $result['creators'][0];

        $this->assertFalse($row['is_profitable']);
        $this->assertEquals(-150.0, $row['net_platform_profit']);
    }

    // ─── multiple communities / totals ─────────────────────────────────────────

    public function test_totals_aggregate_across_multiple_communities(): void
    {
        [$owner1, $comm1] = $this->createCommunityWithOwner('Owner A');
        [$owner2, $comm2] = $this->createCommunityWithOwner('Owner B');

        $this->createPaidPayment($comm1, 1000, 50, 150);
        $this->createPaidPayment($comm2, 2000, 100, 300);

        $result = $this->query->execute();

        $this->assertCount(2, $result['creators']);
        $this->assertEquals(3000.0, $result['totals']['gross']);
        $this->assertEquals(150.0, $result['totals']['processing_fee']);
        $this->assertEquals(450.0, $result['totals']['platform_fee']);
    }

    // ─── subscribers count ─────────────────────────────────────────────────────

    public function test_subscribers_count_is_included(): void
    {
        [$owner, $community] = $this->createCommunityWithOwner();

        CommunityMember::factory()->count(3)->create(['community_id' => $community->id]);

        $result = $this->query->execute();
        $row = $result['creators'][0];

        $this->assertEquals(3, $row['subscribers']);
    }

    // ─── search filter ─────────────────────────────────────────────────────────

    public function test_search_filters_by_community_name(): void
    {
        [$owner1, $comm1] = $this->createCommunityWithOwner('Alice');
        $comm1->update(['name' => 'Unique Alpha Community']);
        [$owner2, $comm2] = $this->createCommunityWithOwner('Bob');
        $comm2->update(['name' => 'Beta Community']);

        $result = $this->query->execute('Alpha');

        $this->assertCount(1, $result['creators']);
        $this->assertEquals($comm1->id, $result['creators'][0]['community_id']);
    }

    public function test_search_filters_by_owner_name(): void
    {
        [$owner1, $comm1] = $this->createCommunityWithOwner('Specific Creator Name');
        [$owner2, $comm2] = $this->createCommunityWithOwner('Other Person');

        $result = $this->query->execute('Specific Creator');

        $this->assertCount(1, $result['creators']);
        $this->assertEquals('Specific Creator Name', $result['creators'][0]['creator_name']);
    }

    public function test_search_filters_by_owner_email(): void
    {
        $owner = User::factory()->create(['email' => 'unique-email-test@example.com']);
        Community::factory()->create(['owner_id' => $owner->id]);
        $this->createCommunityWithOwner('Other Person');

        $result = $this->query->execute('unique-email-test@');

        $this->assertCount(1, $result['creators']);
    }

    // ─── plan filter ───────────────────────────────────────────────────────────

    public function test_plan_filter_excludes_non_matching_creators(): void
    {
        // Both creators are on 'free' plan (no CreatorSubscription)
        [$owner1, $comm1] = $this->createCommunityWithOwner('Free Creator');
        [$owner2, $comm2] = $this->createCommunityWithOwner('Also Free');

        // Filter for 'pro' should return nobody
        $result = $this->query->execute('', 'pro');

        $this->assertEmpty($result['creators']);
    }

    public function test_plan_filter_returns_matching_creators(): void
    {
        [$owner1, $comm1] = $this->createCommunityWithOwner('Free Creator');

        // Filter for 'free' should return the creator
        $result = $this->query->execute('', 'free');

        $this->assertCount(1, $result['creators']);
    }

    // ─── community without owner is skipped ────────────────────────────────────

    // test_community_without_owner_is_skipped is in CreatorAnalyticsOrphanTest
    // because it requires disabling FK constraints outside a transaction.

    // ─── only paid payments are counted ────────────────────────────────────────

    public function test_only_paid_payments_contribute_to_gross(): void
    {
        [$owner, $community] = $this->createCommunityWithOwner();

        $this->createPaidPayment($community, 1000, 50, 150);

        // Create a pending payment - should NOT count
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);
        Payment::create([
            'subscription_id'    => $sub->id,
            'community_id'       => $community->id,
            'user_id'            => $sub->user_id,
            'amount'             => 5000,
            'processing_fee'     => 250,
            'platform_fee'       => 750,
            'currency'           => 'PHP',
            'status'             => Payment::STATUS_PENDING,
            'metadata'           => [],
        ]);

        $result = $this->query->execute();
        $row = $result['creators'][0];

        $this->assertEquals(1000.0, $row['gross']);
    }

    // ─── totals are rounded ────────────────────────────────────────────────────

    public function test_totals_are_rounded_to_two_decimals(): void
    {
        [$owner, $community] = $this->createCommunityWithOwner();

        $this->createPaidPayment($community, 333.33, 16.67, 49.99);

        $result = $this->query->execute();

        foreach ($result['totals'] as $key => $value) {
            // Each total should have at most 2 decimal places
            $this->assertEquals(round($value, 2), $value, "Total '{$key}' is not rounded to 2 decimals");
        }
    }
}
