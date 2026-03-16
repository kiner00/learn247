<?php

namespace Tests\Feature\Web;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
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
