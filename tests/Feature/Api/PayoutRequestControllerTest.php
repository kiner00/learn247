<?php

namespace Tests\Feature\Api;

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

    // ─── storeOwner ───────────────────────────────────────────────────────────

    public function test_store_owner_requires_authentication(): void
    {
        $community = Community::factory()->create();

        $this->postJson("/api/creator/payout-request/{$community->id}", ['amount' => 100])
            ->assertUnauthorized();
    }

    public function test_store_owner_returns_403_for_non_owner(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 499]);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/creator/payout-request/{$community->id}", ['amount' => 100])
            ->assertForbidden();
    }

    public function test_store_owner_validates_amount_is_required(): void
    {
        $owner     = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 499]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/creator/payout-request/{$community->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_store_owner_returns_422_when_no_payout_method_set(): void
    {
        $owner     = User::factory()->create(['payout_method' => null, 'payout_details' => null]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 499]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/creator/payout-request/{$community->id}", ['amount' => 100])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Please set your payout method in Account Settings before requesting a payout.');
    }

    public function test_store_owner_returns_422_when_pending_request_exists(): void
    {
        $owner     = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 499]);

        $subscriber   = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $subscriber->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);

        Payment::create([
            'subscription_id'    => $subscription->id,
            'community_id'       => $community->id,
            'user_id'            => $subscriber->id,
            'amount'             => 499,
            'currency'           => 'PHP',
            'status'             => Payment::STATUS_PAID,
            'provider_reference' => 'pay_test',
            'paid_at'            => now()->subDays(20),
        ]);

        PayoutRequest::create([
            'user_id'         => $owner->id,
            'type'            => PayoutRequest::TYPE_OWNER,
            'community_id'    => $community->id,
            'amount'          => 50,
            'eligible_amount' => 100,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/creator/payout-request/{$community->id}", ['amount' => 50])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You already have a pending payout request for this community.');
    }

    public function test_store_owner_creates_payout_request_successfully(): void
    {
        $owner     = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 499]);

        $subscriber   = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $subscriber->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);

        Payment::create([
            'subscription_id'    => $subscription->id,
            'community_id'       => $community->id,
            'user_id'            => $subscriber->id,
            'amount'             => 499,
            'currency'           => 'PHP',
            'status'             => Payment::STATUS_PAID,
            'provider_reference' => 'pay_test',
            'paid_at'            => now()->subDays(20),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/creator/payout-request/{$community->id}", ['amount' => 100])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Payout request submitted. The admin will review and process it shortly.');

        $this->assertDatabaseHas('payout_requests', [
            'user_id'      => $owner->id,
            'type'         => PayoutRequest::TYPE_OWNER,
            'community_id' => $community->id,
            'status'       => PayoutRequest::STATUS_PENDING,
        ]);
    }

    // ─── storeAffiliate ──────────────────────────────────────────────────────

    public function test_store_affiliate_requires_authentication(): void
    {
        $affiliate = Affiliate::create([
            'community_id'  => Community::factory()->create()->id,
            'user_id'       => User::factory()->create()->id,
            'code'          => 'AFF001',
            'status'        => Affiliate::STATUS_ACTIVE,
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $this->postJson("/api/affiliates/{$affiliate->id}/payout-request", ['amount' => 50])
            ->assertUnauthorized();
    }

    public function test_store_affiliate_returns_403_for_other_user(): void
    {
        $affiliateUser = User::factory()->create();
        $other         = User::factory()->create();
        $affiliate     = Affiliate::create([
            'community_id'   => Community::factory()->create()->id,
            'user_id'        => $affiliateUser->id,
            'code'           => 'AFF002',
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/affiliates/{$affiliate->id}/payout-request", ['amount' => 50])
            ->assertForbidden();
    }

    public function test_store_affiliate_creates_payout_request_successfully(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 499]);
        $affiliate = Affiliate::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'code'           => 'AFF003',
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => Subscription::factory()->create(['community_id' => $community->id])->id,
            'payment_id'        => null,
            'referred_user_id'  => User::factory()->create()->id,
            'sale_amount'       => 499,
            'platform_fee'      => 74.85,
            'commission_amount' => 49.90,
            'creator_amount'    => 374.25,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
        $conversion->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/affiliates/{$affiliate->id}/payout-request", ['amount' => 40])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Payout request submitted. The admin will review and process it shortly.');

        $this->assertDatabaseHas('payout_requests', [
            'user_id'      => $user->id,
            'type'         => PayoutRequest::TYPE_AFFILIATE,
            'affiliate_id' => $affiliate->id,
            'status'       => PayoutRequest::STATUS_PENDING,
        ]);
    }

    // ─── storeAffiliateAll ───────────────────────────────────────────────────

    public function test_store_affiliate_all_requires_authentication(): void
    {
        $this->postJson('/api/affiliates/payout-request/all')
            ->assertUnauthorized();
    }

    public function test_store_affiliate_all_returns_422_when_no_affiliates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/affiliates/payout-request/all')
            ->assertStatus(422)
            ->assertJsonPath('message', 'No affiliates with a valid payout method set.');
    }

    public function test_store_affiliate_all_submits_for_eligible_affiliates(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 499]);

        $affiliate = Affiliate::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'code'           => 'AFF004',
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => Subscription::factory()->create(['community_id' => $community->id])->id,
            'payment_id'        => null,
            'referred_user_id'  => User::factory()->create()->id,
            'sale_amount'       => 499,
            'platform_fee'      => 74.85,
            'commission_amount' => 49.90,
            'creator_amount'    => 374.25,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
        $conversion->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/affiliates/payout-request/all')
            ->assertStatus(201)
            ->assertJsonPath('message', 'Payout request submitted for 1 affiliate program(s).');

        $this->assertDatabaseHas('payout_requests', [
            'user_id'      => $user->id,
            'type'         => PayoutRequest::TYPE_AFFILIATE,
            'affiliate_id' => $affiliate->id,
            'status'       => PayoutRequest::STATUS_PENDING,
        ]);
    }

    public function test_store_affiliate_all_skips_affiliates_with_pending_requests(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 499]);

        $affiliate = Affiliate::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'code'           => 'AFF005',
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => Subscription::factory()->create(['community_id' => $community->id])->id,
            'payment_id'        => null,
            'referred_user_id'  => User::factory()->create()->id,
            'sale_amount'       => 499,
            'platform_fee'      => 74.85,
            'commission_amount' => 49.90,
            'creator_amount'    => 374.25,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);
        $conversion->forceFill(['created_at' => now()->subDays(20)])->save();

        PayoutRequest::create([
            'user_id'         => $user->id,
            'type'            => PayoutRequest::TYPE_AFFILIATE,
            'community_id'    => $community->id,
            'affiliate_id'    => $affiliate->id,
            'amount'          => 40,
            'eligible_amount' => 49.90,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/affiliates/payout-request/all')
            ->assertStatus(422)
            ->assertJsonPath('message', 'No eligible affiliate earnings to request payout for.');
    }
}
