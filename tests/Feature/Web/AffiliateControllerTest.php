<?php

namespace Tests\Feature\Web;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
            $pdo->sqliteCreateFunction('DATE_FORMAT', function ($date, $format) {
                $map = ['%Y' => 'Y', '%m' => 'm', '%d' => 'd', '%Y-%m' => 'Y-m'];
                return date($map[$format] ?? 'Y-m-d', strtotime($date));
            }, 2);
        }
    }

    // ─── index ──────────────────────────────────────────────────────────────

    public function test_affiliate_index_returns_ok_for_user_with_affiliates(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'AFF001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get('/my-affiliates')
            ->assertOk();
    }

    public function test_affiliate_index_returns_ok_for_user_without_affiliates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/my-affiliates')
            ->assertOk();
    }

    public function test_affiliate_index_with_period_filter(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'AFF002',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get('/my-affiliates?period=week')
            ->assertOk();
    }

    public function test_affiliate_index_with_community_filter(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'AFF003',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get("/my-affiliates?community={$community->id}")
            ->assertOk();
    }

    public function test_unauthenticated_cannot_access_affiliate_index(): void
    {
        $this->get('/my-affiliates')
            ->assertRedirect('/login');
    }

    // ─── store ──────────────────────────────────────────────────────────────

    public function test_subscriber_can_join_as_affiliate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 15]);
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => null,
        ]);

        $this->actingAs($user)
            ->post("/communities/{$community->slug}/affiliates")
            ->assertRedirect();

        $this->assertDatabaseHas('affiliates', [
            'user_id' => $user->id,
            'community_id' => $community->id,
        ]);
    }

    public function test_non_subscriber_cannot_join_as_affiliate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 15]);

        $this->actingAs($user)
            ->post("/communities/{$community->slug}/affiliates")
            ->assertSessionHasErrors(['affiliate']);

        $this->assertDatabaseMissing('affiliates', [
            'user_id' => $user->id,
            'community_id' => $community->id,
        ]);
    }

    public function test_cannot_join_affiliate_without_program(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 0]);
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => null,
        ]);

        $this->actingAs($user)
            ->post("/communities/{$community->slug}/affiliates")
            ->assertSessionHasErrors(['affiliate']);
    }

    public function test_cannot_join_affiliate_twice(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 15]);
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => null,
        ]);
        Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'EXISTING',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->post("/communities/{$community->slug}/affiliates")
            ->assertSessionHasErrors(['affiliate']);
    }

    // ─── dashboard (owner only) ─────────────────────────────────────────────

    public function test_owner_can_view_affiliate_dashboard(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'affiliate_commission_rate' => 10]);
        Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'AFF010',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/affiliates")
            ->assertOk();
    }

    public function test_non_owner_cannot_view_affiliate_dashboard(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->get("/communities/{$community->slug}/affiliates")
            ->assertForbidden();
    }

    public function test_dashboard_shows_conversions_data(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'affiliate_commission_rate' => 20]);
        $affUser = User::factory()->create();
        $affiliate = Affiliate::create([
            'user_id' => $affUser->id,
            'community_id' => $community->id,
            'code' => 'AFF011',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 100,
        ]);

        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);
        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 100,
            'creator_amount' => 325,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/affiliates")
            ->assertOk();
    }

    // ─── updatePayout ───────────────────────────────────────────────────────

    public function test_affiliate_owner_can_update_payout(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'AFF020',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->patch("/affiliates/{$affiliate->id}/payout", [
                'payout_method' => 'gcash',
                'payout_details' => '09171234567',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('affiliates', [
            'id' => $affiliate->id,
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
        ]);
    }

    public function test_non_owner_cannot_update_someone_elses_affiliate_payout(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'AFF021',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->actingAs($other)
            ->patch("/affiliates/{$affiliate->id}/payout", [
                'payout_method' => 'gcash',
                'payout_details' => '09171234567',
            ])
            ->assertForbidden();
    }

    public function test_update_payout_validates_method(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'AFF022',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->patch("/affiliates/{$affiliate->id}/payout", [
                'payout_method' => 'bitcoin',
                'payout_details' => 'wallet123',
            ])
            ->assertSessionHasErrors(['payout_method']);
    }

    // ─── markPaid ───────────────────────────────────────────────────────────

    public function test_owner_can_mark_conversion_as_paid(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'AFF030',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 100,
            'total_paid' => 0,
        ]);
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $this->actingAs($owner)
            ->patch("/affiliate-conversions/{$conversion->id}/paid")
            ->assertRedirect();

        $this->assertDatabaseHas('affiliate_conversions', [
            'id' => $conversion->id,
            'status' => AffiliateConversion::STATUS_PAID,
        ]);
        $this->assertEquals(50, $affiliate->fresh()->total_paid);
    }

    public function test_already_paid_conversion_returns_error(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'AFF031',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->actingAs($owner)
            ->patch("/affiliate-conversions/{$conversion->id}/paid")
            ->assertRedirect()
            ->assertSessionHas('error', 'Already marked as paid.');
    }

    public function test_non_owner_cannot_mark_paid(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'AFF032',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $this->actingAs($other)
            ->patch("/affiliate-conversions/{$conversion->id}/paid")
            ->assertForbidden();
    }

    // ─── disburse ───────────────────────────────────────────────────────────

    public function test_owner_can_disburse_payout_via_xendit(): void
    {
        $this->mock(XenditService::class, function ($mock) {
            $mock->shouldReceive('createPayout')
                ->once()
                ->andReturn(['id' => 'po_123', 'status' => 'ACCEPTED']);
        });

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'affiliate_commission_rate' => 10]);
        $affUser = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $affiliate = Affiliate::create([
            'user_id' => $affUser->id,
            'community_id' => $community->id,
            'code' => 'AFF040',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 100,
            'total_paid' => 0,
        ]);
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $this->actingAs($owner)
            ->post("/affiliate-conversions/{$conversion->id}/disburse")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('affiliate_conversions', [
            'id' => $conversion->id,
            'status' => AffiliateConversion::STATUS_PAID,
        ]);
    }

    public function test_disburse_already_paid_returns_error(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'AFF041',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post("/affiliate-conversions/{$conversion->id}/disburse")
            ->assertRedirect()
            ->assertSessionHas('error', 'Already paid.');
    }

    public function test_non_owner_cannot_disburse(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'AFF042',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $this->actingAs($other)
            ->post("/affiliate-conversions/{$conversion->id}/disburse")
            ->assertForbidden();
    }

    public function test_disburse_handles_xendit_failure(): void
    {
        $this->mock(XenditService::class, function ($mock) {
            $mock->shouldReceive('createPayout')
                ->once()
                ->andThrow(new \RuntimeException('Insufficient balance'));
        });

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'affiliate_commission_rate' => 10]);
        $affUser = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $affiliate = Affiliate::create([
            'user_id' => $affUser->id,
            'community_id' => $community->id,
            'code' => 'AFF043',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 100,
            'total_paid' => 0,
        ]);
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $this->actingAs($owner)
            ->post("/affiliate-conversions/{$conversion->id}/disburse")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('affiliate_conversions', [
            'id' => $conversion->id,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);
    }
}
