<?php

namespace Tests\Feature\Queries;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_owner_with_eligible_payments(): void
    {
        $community = Community::factory()->create();
        $sub       = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => User::factory()->create()->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);
        Payment::create([
            'subscription_id' => $sub->id,
            'community_id'    => $community->id,
            'user_id'         => $sub->user_id,
            'amount'          => 1000,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now()->subDays(20),
        ]);

        $query = new CalculateEligibility();
        [$eligible, $locked, $nextDate] = $query->forOwner($community);

        $this->assertGreaterThan(0, $eligible);
        $this->assertEquals(0, $locked);
        $this->assertNull($nextDate);
    }

    public function test_for_owner_with_locked_payments(): void
    {
        $community = Community::factory()->create();
        $sub       = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => User::factory()->create()->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);
        Payment::create([
            'subscription_id' => $sub->id,
            'community_id'    => $community->id,
            'user_id'         => $sub->user_id,
            'amount'          => 1000,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now()->subDays(5),
        ]);

        $query = new CalculateEligibility();
        [$eligible, $locked, $nextDate] = $query->forOwner($community);

        $this->assertEquals(0, $eligible);
        $this->assertGreaterThan(0, $locked);
        $this->assertNotNull($nextDate);
    }

    public function test_for_owner_deducts_already_paid(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $sub       = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => User::factory()->create()->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);
        Payment::create([
            'subscription_id' => $sub->id,
            'community_id'    => $community->id,
            'user_id'         => $sub->user_id,
            'amount'          => 1000,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now()->subDays(20),
        ]);

        OwnerPayout::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'amount'       => 500,
            'status'       => 'completed',
            'paid_at'      => now()->subDays(10),
        ]);

        $query = new CalculateEligibility();
        [$eligible, $locked, $nextDate] = $query->forOwner($community);

        $this->assertLessThan(500, $eligible);
    }

    public function test_for_affiliate_with_eligible_conversions(): void
    {
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'user_id'      => User::factory()->create()->id,
            'community_id' => $community->id,
            'code'         => 'ELIG001',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => User::factory()->create()->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id'    => $community->id,
            'user_id'         => $sub->user_id,
            'amount'          => 500,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now()->subDays(20),
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $sub->id,
            'payment_id'        => $payment->id,
            'referred_user_id'  => $sub->user_id,
            'sale_amount'       => 500,
            'platform_fee'      => 75,
            'commission_amount' => 50,
            'creator_amount'    => 375,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
        $conversion->created_at = now()->subDays(20);
        $conversion->save();

        $query    = new CalculateEligibility();
        $eligible = $query->forAffiliate($affiliate);

        $this->assertEquals(50, $eligible);
    }

    public function test_for_affiliate_deducts_in_flight_requests(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $affiliate = Affiliate::create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
            'code'         => 'ELIG002',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => User::factory()->create()->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id'    => $community->id,
            'user_id'         => $sub->user_id,
            'amount'          => 500,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now()->subDays(20),
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $sub->id,
            'payment_id'        => $payment->id,
            'referred_user_id'  => $sub->user_id,
            'sale_amount'       => 500,
            'platform_fee'      => 75,
            'commission_amount' => 100,
            'creator_amount'    => 325,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
        $conversion->created_at = now()->subDays(20);
        $conversion->save();

        PayoutRequest::create([
            'user_id'         => $user->id,
            'affiliate_id'    => $affiliate->id,
            'type'            => PayoutRequest::TYPE_AFFILIATE,
            'amount'          => 60,
            'eligible_amount' => 100,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        $query    = new CalculateEligibility();
        $eligible = $query->forAffiliate($affiliate);

        $this->assertEquals(40, $eligible);
    }

    public function test_for_affiliate_recent_conversions_not_eligible(): void
    {
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'user_id'      => User::factory()->create()->id,
            'community_id' => $community->id,
            'code'         => 'ELIG003',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => User::factory()->create()->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id'    => $community->id,
            'user_id'         => $sub->user_id,
            'amount'          => 500,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now(),
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $sub->id,
            'payment_id'        => $payment->id,
            'referred_user_id'  => $sub->user_id,
            'sale_amount'       => 500,
            'platform_fee'      => 75,
            'commission_amount' => 50,
            'creator_amount'    => 375,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
        $conversion->created_at = now()->subDays(5);
        $conversion->save();

        $query    = new CalculateEligibility();
        $eligible = $query->forAffiliate($affiliate);

        $this->assertEquals(0, $eligible);
    }
}
