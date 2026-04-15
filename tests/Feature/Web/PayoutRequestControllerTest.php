<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Web\PayoutRequestController;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── storeOwner ─────────────────────────────────────────────────────────────

    public function test_owner_can_submit_payout_request(): void
    {
        $owner = User::factory()->create([
            'payout_method'   => 'gcash',
            'payout_details'  => '09171234567',
            'kyc_verified_at' => now(),
        ]);
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $owner->id,
            'amount'          => 1000,
            'status'          => 'paid',
            'paid_at'         => now()->subDays(20),
        ]);

        $response = $this->actingAs($owner)->post("/creator/payout-request/{$community->id}", [
            'amount' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payout_requests', [
            'user_id'      => $owner->id,
            'community_id' => $community->id,
            'type'         => PayoutRequest::TYPE_OWNER,
            'status'       => PayoutRequest::STATUS_PENDING,
        ]);
    }

    public function test_non_owner_cannot_submit_owner_payout_request(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($other)->post("/creator/payout-request/{$community->id}", [
            'amount' => 100,
        ]);

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_from_owner_payout_request(): void
    {
        $community = Community::factory()->create();

        $response = $this->post("/creator/payout-request/{$community->id}", [
            'amount' => 100,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_owner_payout_fails_without_payout_method(): void
    {
        $owner = User::factory()->create([
            'payout_method'  => null,
            'payout_details' => null,
        ]);
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $owner->id,
            'amount'          => 1000,
            'status'          => 'paid',
            'paid_at'         => now()->subDays(20),
        ]);

        $response = $this->actingAs($owner)->post("/creator/payout-request/{$community->id}", [
            'amount' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_owner_payout_fails_when_pending_request_exists(): void
    {
        $owner = User::factory()->create([
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        PayoutRequest::create([
            'user_id'         => $owner->id,
            'type'            => PayoutRequest::TYPE_OWNER,
            'community_id'    => $community->id,
            'amount'          => 500,
            'eligible_amount' => 500,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($owner)->post("/creator/payout-request/{$community->id}", [
            'amount' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_owner_payout_validates_amount_required(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->post("/creator/payout-request/{$community->id}", []);

        $response->assertSessionHasErrors('amount');
    }

    // ─── storeAffiliate ─────────────────────────────────────────────────────────

    public function test_affiliate_can_submit_payout_request(): void
    {
        $user      = User::factory()->create(['kyc_verified_at' => now()]);
        $referred  = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referred->id,
        ]);

        $affiliate = Affiliate::create([
            'community_id'  => $community->id,
            'user_id'       => $user->id,
            'code'          => 'AFF-TEST',
            'status'        => Affiliate::STATUS_ACTIVE,
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $this->travel(-20)->days();
        AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $subscription->id,
            'referred_user_id'  => $referred->id,
            'sale_amount'       => 499,
            'platform_fee'      => 74.85,
            'commission_amount' => 200,
            'creator_amount'    => 224.15,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
        $this->travelBack();

        $response = $this->actingAs($user)->post("/affiliates/{$affiliate->id}/payout-request", [
            'amount' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payout_requests', [
            'affiliate_id' => $affiliate->id,
            'type'         => PayoutRequest::TYPE_AFFILIATE,
            'status'       => PayoutRequest::STATUS_PENDING,
        ]);
    }

    public function test_non_owner_of_affiliate_cannot_submit_affiliate_payout(): void
    {
        $user      = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create();

        $affiliate = Affiliate::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'code'           => 'AFF-DENY',
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $response = $this->actingAs($other)->post("/affiliates/{$affiliate->id}/payout-request", [
            'amount' => 100,
        ]);

        $response->assertForbidden();
    }

    public function test_affiliate_payout_fails_without_payout_method(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        $affiliate = Affiliate::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'code'           => 'AFF-NOPM',
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => null,
            'payout_details' => null,
        ]);

        $response = $this->actingAs($user)->post("/affiliates/{$affiliate->id}/payout-request", [
            'amount' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ─── storeAffiliateAll ──────────────────────────────────────────────────────

    public function test_affiliate_all_submits_for_eligible_affiliates(): void
    {
        $user       = User::factory()->create();
        $referred   = User::factory()->create();
        $community  = Community::factory()->paid()->create();

        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referred->id,
        ]);

        $affiliate = Affiliate::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'code'           => 'AFF-ALL1',
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $this->travel(-20)->days();
        AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $subscription->id,
            'referred_user_id'  => $referred->id,
            'sale_amount'       => 499,
            'platform_fee'      => 74.85,
            'commission_amount' => 200,
            'creator_amount'    => 224.15,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
        $this->travelBack();

        $response = $this->actingAs($user)->post('/affiliates/payout-request/all');

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payout_requests', [
            'affiliate_id' => $affiliate->id,
            'type'         => PayoutRequest::TYPE_AFFILIATE,
        ]);
    }

    public function test_affiliate_all_returns_error_when_no_valid_affiliates(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/affiliates/payout-request/all');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_affiliate_all_skips_affiliates_with_pending_requests(): void
    {
        $user      = User::factory()->create();
        $referred  = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referred->id,
        ]);

        $affiliate = Affiliate::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'code'           => 'AFF-PND',
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $this->travel(-20)->days();
        AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $subscription->id,
            'referred_user_id'  => $referred->id,
            'sale_amount'       => 499,
            'platform_fee'      => 74.85,
            'commission_amount' => 200,
            'creator_amount'    => 224.15,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
        $this->travelBack();

        PayoutRequest::create([
            'user_id'         => $user->id,
            'type'            => PayoutRequest::TYPE_AFFILIATE,
            'community_id'    => $community->id,
            'affiliate_id'    => $affiliate->id,
            'amount'          => 100,
            'eligible_amount' => 200,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->post('/affiliates/payout-request/all');

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(1, PayoutRequest::where('affiliate_id', $affiliate->id)->count());
    }

    // ─── ownerEligibility (deprecated static) ───────────────────────────────────

    public function test_owner_eligibility_returns_correct_structure(): void
    {
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);

        Payment::create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $owner->id,
            'amount'          => 1000,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now()->subDays(30),
        ]);

        $result = PayoutRequestController::ownerEligibility($community);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
    }

    // ─── affiliateEligibility (deprecated static) ───────────────────────────────

    public function test_affiliate_eligibility_returns_float(): void
    {
        $user      = User::factory()->create();
        $referred  = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referred->id,
        ]);

        $affiliate = Affiliate::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'code'           => 'AFF-ELIG',
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $this->travel(-25)->days();
        AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $subscription->id,
            'referred_user_id'  => $referred->id,
            'sale_amount'       => 500,
            'platform_fee'      => 75,
            'commission_amount' => 100,
            'creator_amount'    => 325,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
        $this->travelBack();

        $result = PayoutRequestController::affiliateEligibility($affiliate);

        $this->assertIsFloat($result);
    }
}
