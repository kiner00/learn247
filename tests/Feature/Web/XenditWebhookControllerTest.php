<?php

namespace Tests\Feature\Web;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XenditWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function webhookHeaders(string $token): array
    {
        return ['X-CALLBACK-TOKEN' => $token];
    }

    // ─── Auth / Token Verification ──────────────────────────────────────────────

    public function test_invalid_callback_token_returns_401(): void
    {
        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.succeeded',
            'data'  => ['id' => 'po_123', 'reference_id' => 'ref_123'],
        ], $this->webhookHeaders('wrong-token'));

        $response->assertStatus(401);
    }

    public function test_missing_callback_token_returns_401(): void
    {
        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.succeeded',
            'data'  => ['id' => 'po_123'],
        ]);

        $response->assertStatus(401);
    }

    // ─── Owner Payout Webhook ───────────────────────────────────────────────────

    public function test_payout_succeeded_updates_owner_payout_status(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $ownerPayout = OwnerPayout::create([
            'community_id'     => $community->id,
            'user_id'          => $owner->id,
            'amount'           => 500,
            'status'           => 'pending',
            'xendit_reference' => 'po_owner_123',
        ]);

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.succeeded',
            'data'  => [
                'id'           => 'po_owner_123',
                'reference_id' => 'ref_owner_123',
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
        $this->assertEquals('succeeded', $ownerPayout->fresh()->status);
    }

    public function test_payout_failed_updates_owner_payout_status(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $ownerPayout = OwnerPayout::create([
            'community_id'     => $community->id,
            'user_id'          => $owner->id,
            'amount'           => 500,
            'status'           => 'pending',
            'xendit_reference' => 'po_fail_123',
        ]);

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.failed',
            'data'  => [
                'id'           => 'po_fail_123',
                'reference_id' => 'ref_fail_123',
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
        $this->assertEquals('failed', $ownerPayout->fresh()->status);
    }

    // ─── Affiliate Conversion Webhook ───────────────────────────────────────────

    public function test_payout_succeeded_updates_affiliate_conversion_to_paid(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $user      = User::factory()->create();
        $referred  = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referred->id,
        ]);

        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'         => 'AFF-WH1',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $subscription->id,
            'referred_user_id'  => $referred->id,
            'sale_amount'       => 499,
            'platform_fee'      => 74.85,
            'commission_amount' => 100,
            'creator_amount'    => 324.15,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);

        $referenceId = "payout-{$conversion->id}-" . time();

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.succeeded',
            'data'  => [
                'id'           => 'po_aff_123',
                'reference_id' => $referenceId,
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
        $conversion->refresh();
        $this->assertEquals(AffiliateConversion::STATUS_PAID, $conversion->status);
        $this->assertNotNull($conversion->paid_at);
    }

    public function test_payout_failed_resets_affiliate_conversion_to_pending(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $user      = User::factory()->create();
        $referred  = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referred->id,
        ]);

        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'         => 'AFF-WH2',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $subscription->id,
            'referred_user_id'  => $referred->id,
            'sale_amount'       => 499,
            'platform_fee'      => 74.85,
            'commission_amount' => 100,
            'creator_amount'    => 324.15,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);

        $referenceId = "payout-{$conversion->id}-" . time();

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.failed',
            'data'  => [
                'id'           => 'po_aff_fail',
                'reference_id' => $referenceId,
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
        $this->assertEquals(AffiliateConversion::STATUS_PENDING, $conversion->fresh()->status);
    }

    // ─── Owner PayoutRequest via req- reference_id ──────────────────────────────

    public function test_payout_succeeded_updates_owner_payout_request_to_paid(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $payoutRequest = PayoutRequest::create([
            'user_id'         => $user->id,
            'type'            => PayoutRequest::TYPE_OWNER,
            'community_id'    => $community->id,
            'amount'          => 1000,
            'eligible_amount' => 1000,
            'status'          => PayoutRequest::STATUS_APPROVED,
        ]);

        $referenceId = "req-{$payoutRequest->id}-" . time();

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.succeeded',
            'data'  => [
                'id'           => 'po_owner_req_123',
                'reference_id' => $referenceId,
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
        $this->assertEquals(PayoutRequest::STATUS_PAID, $payoutRequest->fresh()->status);
    }

    public function test_payout_failed_resets_owner_payout_request_to_pending(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $payoutRequest = PayoutRequest::create([
            'user_id'         => $user->id,
            'type'            => PayoutRequest::TYPE_OWNER,
            'community_id'    => $community->id,
            'amount'          => 1000,
            'eligible_amount' => 1000,
            'status'          => PayoutRequest::STATUS_APPROVED,
        ]);

        $referenceId = "req-{$payoutRequest->id}-" . time();

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.failed',
            'data'  => [
                'id'           => 'po_owner_req_fail',
                'reference_id' => $referenceId,
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
        $this->assertEquals(PayoutRequest::STATUS_PENDING, $payoutRequest->fresh()->status);
    }

    // ─── Affiliate PayoutRequest via req- reference_id ─────────────────────────

    public function test_payout_succeeded_marks_affiliate_payout_request_paid_and_conversions(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $user      = User::factory()->create();
        $referred  = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referred->id,
        ]);

        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'         => 'AFF-REQ-1',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $payoutRequest = PayoutRequest::create([
            'user_id'         => $user->id,
            'type'            => PayoutRequest::TYPE_AFFILIATE,
            'affiliate_id'    => $affiliate->id,
            'amount'          => 200,
            'eligible_amount' => 200,
            'status'          => PayoutRequest::STATUS_APPROVED,
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $subscription->id,
            'referred_user_id'  => $referred->id,
            'sale_amount'       => 499,
            'platform_fee'      => 74.85,
            'commission_amount' => 100,
            'creator_amount'    => 324.15,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);

        $referenceId = "req-{$payoutRequest->id}-" . time();

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.succeeded',
            'data'  => [
                'id'           => 'po_aff_req_succ',
                'reference_id' => $referenceId,
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
        $this->assertEquals(PayoutRequest::STATUS_PAID, $payoutRequest->fresh()->status);
        $this->assertEquals(AffiliateConversion::STATUS_PAID, $conversion->fresh()->status);
        $this->assertNotNull($conversion->fresh()->paid_at);
    }

    public function test_payout_failed_resets_affiliate_payout_request_to_pending(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'         => 'AFF-REQ-2',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $payoutRequest = PayoutRequest::create([
            'user_id'         => $user->id,
            'type'            => PayoutRequest::TYPE_AFFILIATE,
            'affiliate_id'    => $affiliate->id,
            'amount'          => 200,
            'eligible_amount' => 200,
            'status'          => PayoutRequest::STATUS_APPROVED,
        ]);

        $referenceId = "req-{$payoutRequest->id}-" . time();

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.failed',
            'data'  => [
                'id'           => 'po_aff_req_fail',
                'reference_id' => $referenceId,
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
        $this->assertEquals(PayoutRequest::STATUS_PENDING, $payoutRequest->fresh()->status);
    }

    // ─── OwnerPayout matched by reference_id ───────────────────────────────────

    public function test_payout_succeeded_matches_owner_payout_by_reference_id(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $ownerPayout = OwnerPayout::create([
            'community_id'     => $community->id,
            'user_id'          => $owner->id,
            'amount'           => 500,
            'status'           => 'pending',
            'xendit_reference' => 'ref_match_by_ref',
        ]);

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.succeeded',
            'data'  => [
                'id'           => 'po_unrelated_id',
                'reference_id' => 'ref_match_by_ref',
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
        $this->assertEquals('succeeded', $ownerPayout->fresh()->status);
    }

    // ─── req- reference with non-existent PayoutRequest ────────────────────────

    public function test_req_reference_with_nonexistent_payout_request_falls_through(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.succeeded',
            'data'  => [
                'id'           => 'po_unknown',
                'reference_id' => 'req-99999-' . time(),
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
    }

    // ─── payout- reference with non-existent conversion ────────────────────────

    public function test_payout_reference_with_nonexistent_conversion_falls_through(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.succeeded',
            'data'  => [
                'id'           => 'po_unknown',
                'reference_id' => 'payout-99999-' . time(),
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
    }

    // ─── Unrecognized Events ────────────────────────────────────────────────────

    public function test_unrecognized_event_returns_200(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.created',
            'data'  => ['id' => 'po_123', 'reference_id' => 'ref_123'],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
    }

    public function test_no_matching_record_returns_200(): void
    {
        config(['services.xendit.callback_token' => 'test-callback-token']);

        $response = $this->postJson('/webhooks/xendit/payouts', [
            'event' => 'payout.succeeded',
            'data'  => [
                'id'           => 'po_nonexistent',
                'reference_id' => 'ref_nonexistent',
            ],
        ], $this->webhookHeaders('test-callback-token'));

        $response->assertStatus(200);
    }
}
