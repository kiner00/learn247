<?php

namespace Tests\Feature\Services;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payout\OwnerEarningsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerEarningsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private OwnerEarningsCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new OwnerEarningsCalculator();
    }

    // ── Zero state ────────────────────────────────────────────────────────────

    public function test_returns_zero_gross_with_no_payments(): void
    {
        $community = Community::factory()->create();

        $result = $this->calc->forCommunity($community);

        $this->assertEquals(0.0, $result['gross']);
        $this->assertEquals(0.0, $result['platform_fee']);
        $this->assertEquals(0.0, $result['affiliate_commission']);
        $this->assertEquals(0.0, $result['earned']);
        $this->assertEquals(0.0, $result['paid']);
        $this->assertEquals(0.0, $result['pending']);
    }

    // ── Gross & platform fee ──────────────────────────────────────────────────

    public function test_sums_only_paid_payments(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        $sub       = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Payment::create(['subscription_id' => $sub->id, 'community_id' => $community->id, 'user_id' => $member->id,
            'amount' => 500, 'currency' => 'PHP', 'status' => Payment::STATUS_PAID, 'metadata' => [], 'paid_at' => now()]);

        Payment::create(['subscription_id' => $sub->id, 'community_id' => $community->id, 'user_id' => $member->id,
            'amount' => 300, 'currency' => 'PHP', 'status' => Payment::STATUS_PENDING, 'metadata' => []]);

        $result = $this->calc->forCommunity($community);

        $this->assertSame(500.0, $result['gross']);
    }

    public function test_platform_fee_is_gross_times_rate(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        $sub       = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Payment::create(['subscription_id' => $sub->id, 'community_id' => $community->id, 'user_id' => $member->id,
            'amount' => 1000, 'currency' => 'PHP', 'status' => Payment::STATUS_PAID, 'metadata' => [], 'paid_at' => now()]);

        $result = $this->calc->forCommunity($community);

        $expectedFee  = round(1000 * $community->platformFeeRate(), 2);
        $this->assertEquals($expectedFee, $result['platform_fee']);
        $this->assertEquals($community->platformFeeRate(), $result['platform_fee_rate']);
    }

    // ── Affiliate commissions ─────────────────────────────────────────────────

    public function test_subtracts_affiliate_commission_from_earned(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        $sub       = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Payment::create(['subscription_id' => $sub->id, 'community_id' => $community->id, 'user_id' => $member->id,
            'amount' => 1000, 'currency' => 'PHP', 'status' => Payment::STATUS_PAID, 'metadata' => [], 'paid_at' => now()]);

        $affiliateUser = User::factory()->create();
        $affiliate     = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
            'code'         => 'AFF01',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        AffiliateConversion::create([
            'affiliate_id'     => $affiliate->id,
            'subscription_id'  => $sub->id,
            'referred_user_id' => $member->id,
            'sale_amount'      => 1000,
            'platform_fee'     => 98,
            'commission_amount'=> 100,
            'creator_amount'   => 802,
        ]);

        $result = $this->calc->forCommunity($community);

        $this->assertSame(100.0, $result['affiliate_commission']);

        $platformFee = round(1000 * $community->platformFeeRate(), 2);
        $this->assertEquals(round(1000 - $platformFee - 100, 2), $result['earned']);
    }

    public function test_only_counts_commissions_from_this_community(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $other     = Community::factory()->create();

        // Conversion for the OTHER community's affiliate
        $affUser   = User::factory()->create();
        $affiliate = Affiliate::create(['community_id' => $other->id, 'user_id' => $affUser->id, 'code' => 'OTH', 'status' => Affiliate::STATUS_ACTIVE]);
        $member    = User::factory()->create();
        $sub       = Subscription::factory()->active()->create(['community_id' => $other->id, 'user_id' => $member->id]);

        AffiliateConversion::create([
            'affiliate_id'     => $affiliate->id,
            'subscription_id'  => $sub->id,
            'referred_user_id' => $member->id,
            'sale_amount'      => 500,
            'platform_fee'     => 49,
            'commission_amount'=> 50,
            'creator_amount'   => 401,
        ]);

        $result = $this->calc->forCommunity($community);

        $this->assertSame(0.0, $result['affiliate_commission']);
    }

    // ── Paid & pending ────────────────────────────────────────────────────────

    public function test_paid_is_sum_of_non_failed_payouts(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        $sub       = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Payment::create(['subscription_id' => $sub->id, 'community_id' => $community->id, 'user_id' => $member->id,
            'amount' => 2000, 'currency' => 'PHP', 'status' => Payment::STATUS_PAID, 'metadata' => [], 'paid_at' => now()]);

        OwnerPayout::create(['community_id' => $community->id, 'user_id' => $owner->id,
            'amount' => 500, 'status' => 'accepted', 'xendit_reference' => 'ref1', 'paid_at' => now()]);

        OwnerPayout::create(['community_id' => $community->id, 'user_id' => $owner->id,
            'amount' => 200, 'status' => 'failed', 'xendit_reference' => 'ref2', 'paid_at' => now()]);

        $result = $this->calc->forCommunity($community);

        $this->assertSame(500.0, $result['paid']); // failed payout excluded
    }

    public function test_pending_is_floored_at_zero_when_paid_exceeds_earned(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        // No income, but a payout recorded
        OwnerPayout::create(['community_id' => $community->id, 'user_id' => $owner->id,
            'amount' => 100, 'status' => 'accepted', 'xendit_reference' => 'ref1', 'paid_at' => now()]);

        $result = $this->calc->forCommunity($community);

        $this->assertEquals(0.0, $result['pending']); // must not go negative
    }

    // ── Correct result structure ──────────────────────────────────────────────

    public function test_result_contains_all_expected_keys(): void
    {
        $community = Community::factory()->create();

        $result = $this->calc->forCommunity($community);

        $this->assertArrayHasKey('gross', $result);
        $this->assertArrayHasKey('platform_fee_rate', $result);
        $this->assertArrayHasKey('platform_fee', $result);
        $this->assertArrayHasKey('affiliate_commission', $result);
        $this->assertArrayHasKey('earned', $result);
        $this->assertArrayHasKey('paid', $result);
        $this->assertArrayHasKey('pending', $result);
    }

    public function test_parts_sum_correctly(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        $sub       = Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Payment::create(['subscription_id' => $sub->id, 'community_id' => $community->id, 'user_id' => $member->id,
            'amount' => 1000, 'currency' => 'PHP', 'status' => Payment::STATUS_PAID, 'metadata' => [], 'paid_at' => now()]);

        $result = $this->calc->forCommunity($community);

        // earned = gross - platform_fee - commission
        $this->assertEquals(
            round($result['gross'] - $result['platform_fee'] - $result['affiliate_commission'], 2),
            $result['earned']
        );
    }
}
