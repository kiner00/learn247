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

    public function test_for_owner_rejected_request_restores_eligible_balance(): void
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

        $query = new CalculateEligibility();

        // Get baseline eligible amount
        [$eligibleBefore] = $query->forOwner($community);
        $this->assertGreaterThan(0, $eligibleBefore);

        // Create a pending payout request — should reduce eligible balance
        $payoutRequest = PayoutRequest::create([
            'user_id'         => $owner->id,
            'type'            => PayoutRequest::TYPE_OWNER,
            'community_id'    => $community->id,
            'amount'          => $eligibleBefore,
            'eligible_amount' => $eligibleBefore,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        [$eligibleWhilePending] = $query->forOwner($community);
        $this->assertEquals(0, $eligibleWhilePending, 'Eligible balance should be 0 while request is pending');

        // Reject the payout request — balance should be fully restored
        $payoutRequest->update([
            'status'           => PayoutRequest::STATUS_REJECTED,
            'rejection_reason' => 'Testing rejection',
            'processed_at'     => now(),
        ]);

        [$eligibleAfterReject] = $query->forOwner($community);
        $this->assertEquals($eligibleBefore, $eligibleAfterReject, 'Eligible balance should be fully restored after rejection');
    }

    public function test_for_affiliate_rejected_request_restores_eligible_balance(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $affiliate = Affiliate::create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
            'code'         => 'REJTEST',
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

        $query = new CalculateEligibility();

        // Baseline
        $eligibleBefore = $query->forAffiliate($affiliate);
        $this->assertEquals(100, $eligibleBefore);

        // Create pending request
        $payoutRequest = PayoutRequest::create([
            'user_id'         => $user->id,
            'affiliate_id'    => $affiliate->id,
            'type'            => PayoutRequest::TYPE_AFFILIATE,
            'amount'          => 100,
            'eligible_amount' => 100,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        $eligibleWhilePending = $query->forAffiliate($affiliate);
        $this->assertEquals(0, $eligibleWhilePending, 'Eligible balance should be 0 while request is pending');

        // Reject — balance should restore
        $payoutRequest->update([
            'status'           => PayoutRequest::STATUS_REJECTED,
            'rejection_reason' => 'Testing rejection',
            'processed_at'     => now(),
        ]);

        $eligibleAfterReject = $query->forAffiliate($affiliate);
        $this->assertEquals(100, $eligibleAfterReject, 'Eligible balance should be fully restored after rejection');
    }

    public function test_for_owner_can_submit_new_request_after_rejection(): void
    {
        $owner     = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
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

        $query = new CalculateEligibility();
        [$eligibleAmount] = $query->forOwner($community);

        // Create and reject a payout request
        $payoutRequest = PayoutRequest::create([
            'user_id'         => $owner->id,
            'type'            => PayoutRequest::TYPE_OWNER,
            'community_id'    => $community->id,
            'amount'          => $eligibleAmount,
            'eligible_amount' => $eligibleAmount,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        $payoutRequest->update([
            'status'           => PayoutRequest::STATUS_REJECTED,
            'rejection_reason' => 'Insufficient docs',
            'processed_at'     => now(),
        ]);

        // Should be able to submit a new request for the same amount
        $action = new \App\Actions\Payout\RequestOwnerPayout($query);
        $result = $action->execute($owner, $community, $eligibleAmount);

        $this->assertTrue($result['success'], 'User should be able to submit a new payout request after rejection');
        $this->assertDatabaseHas('payout_requests', [
            'user_id'      => $owner->id,
            'community_id' => $community->id,
            'status'       => PayoutRequest::STATUS_PENDING,
            'amount'       => $eligibleAmount,
        ]);
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
