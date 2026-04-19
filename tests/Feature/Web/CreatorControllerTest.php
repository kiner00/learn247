<?php

namespace Tests\Feature\Web;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Analytics\CreatorAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreatorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createPaidPayment(Community $community, User $payer, float $amount, ?string $paidAt = null): Payment
    {
        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $payer->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        return Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'community_id' => $community->id,
            'user_id' => $payer->id,
            'amount' => $amount,
            'status' => Payment::STATUS_PAID,
            'paid_at' => $paidAt ?? now()->subDays(20),
        ]);
    }

    private function createPayoutRequest(User $owner, Community $community, float $amount, string $status = PayoutRequest::STATUS_PENDING): PayoutRequest
    {
        return PayoutRequest::create([
            'user_id' => $owner->id,
            'community_id' => $community->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'status' => $status,
            'amount' => $amount,
            'eligible_amount' => $amount,
        ]);
    }

    // ─── dashboard ─────────────────────────────────────────────────────────────

    public function test_owner_can_view_creator_dashboard(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price' => 100,
        ]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities')
            ->has('requestHistory')
            ->has('payoutMethod')
            ->has('payoutDetails')
        );
    }

    public function test_dashboard_shows_community_revenue_data(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price' => 200,
        ]);

        $payer = User::factory()->create();
        $this->createPaidPayment($community, $payer, 200.00);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities', 1)
            ->where('communities.0.community_id', $community->id)
            ->where('communities.0.community_name', $community->name)
            ->where('communities.0.gross', fn ($v) => (float) $v === 200.0)
            ->where('communities.0.platform_fee', fn ($v) => (float) $v === round(200 * 0.098, 2)) // 9.8% (free plan)
        );
    }

    public function test_dashboard_includes_affiliate_commissions_in_calculations(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price' => 100,
        ]);

        $payer = User::factory()->create();
        $payment = $this->createPaidPayment($community, $payer, 100.00);

        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'code' => 'TEST123',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $payment->subscription_id,
            'referred_user_id' => $payer->id,
            'commission_amount' => 10.00,
            'sale_amount' => 100.00,
            'platform_fee' => 15.00,
            'creator_amount' => 75.00,
        ]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->where('communities.0.commissions', fn ($v) => (float) $v === 10.0)
            ->where('communities.0.earned', fn ($v) => (float) $v === round(100 - round(100 * 0.098, 2) - 10, 2)) // gross - platform_fee(9.8%) - commission
        );
    }

    public function test_dashboard_includes_paid_and_eligible_amounts(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price' => 100,
        ]);

        $payer = User::factory()->create();
        $this->createPaidPayment($community, $payer, 100.00);

        OwnerPayout::create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'amount' => 50.00,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->where('communities.0.paid', fn ($v) => (float) $v === 50.0)
        );
    }

    public function test_dashboard_includes_pending_payout_request(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price' => 100,
        ]);

        $request = $this->createPayoutRequest($owner, $community, 25.00);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->where('communities.0.pending_request.id', $request->id)
            ->where('communities.0.pending_request.amount', fn ($v) => (float) $v === 25.0)
        );
    }

    public function test_dashboard_includes_recent_payments(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price' => 50,
        ]);

        $payer = User::factory()->create(['name' => 'Test Payer']);
        $this->createPaidPayment($community, $payer, 50.00, now()->subDay());

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities.0.recent_payments', 1)
        );
    }

    public function test_dashboard_includes_request_history(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price' => 100,
        ]);

        $this->createPayoutRequest($owner, $community, 40.00, PayoutRequest::STATUS_APPROVED);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('requestHistory', 1)
        );
    }

    public function test_dashboard_excludes_free_communities(): void
    {
        $owner = User::factory()->create();
        Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities', 0)
        );
    }

    public function test_user_with_no_communities_sees_empty_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities', 0)
            ->has('requestHistory', 0)
        );
    }

    public function test_owner_does_not_see_other_owners_communities(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        Community::factory()->create(['owner_id' => $owner->id, 'price' => 100]);
        Community::factory()->create(['owner_id' => $otherOwner->id, 'price' => 100]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities', 1)
        );
    }

    public function test_dashboard_shows_payout_method_and_details(): void
    {
        $owner = User::factory()->create([
            'payout_method' => 'bank_transfer',
            'payout_details' => 'BCA 1234567890',
        ]);
        Community::factory()->create(['owner_id' => $owner->id, 'price' => 100]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->where('payoutMethod', 'bank_transfer')
            ->where('payoutDetails', 'BCA 1234567890')
        );
    }

    public function test_guest_cannot_access_creator_dashboard(): void
    {
        $response = $this->get('/creator/dashboard');

        $response->assertRedirect('/login');
    }

    // ─── plan ──────────────────────────────────────────────────────────────────

    public function test_user_can_view_plan_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/creator/plan');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Plan')
            ->has('basicPrice')
            ->has('proPrice')
            ->has('currentPlan')
        );
    }

    public function test_plan_page_shows_custom_pricing(): void
    {
        $user = User::factory()->create();

        \App\Models\Setting::set('creator_plan_basic_price', 299);
        \App\Models\Setting::set('creator_plan_pro_price', 999);

        $response = $this->actingAs($user)->get('/creator/plan');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('basicPrice', 299)
            ->where('proPrice', 999)
        );
    }

    public function test_guest_cannot_view_plan_page(): void
    {
        $this->get('/creator/plan')->assertRedirect('/login');
    }

    // ─── planCheckout ─────────────────────────────────────────────────────────

    public function test_user_can_start_plan_checkout(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'inv_creator_123',
                'invoice_url' => 'https://checkout.xendit.co/inv_creator_123',
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/creator/plan/checkout', [
            'plan' => 'basic',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['checkout_url']);
    }

    public function test_plan_checkout_validates_plan_field(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/creator/plan/checkout', [
            'plan' => 'invalid',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('plan');
    }

    public function test_plan_checkout_requires_plan_field(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/creator/plan/checkout', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('plan');
    }

    public function test_guest_cannot_start_plan_checkout(): void
    {
        $this->postJson('/creator/plan/checkout', ['plan' => 'basic'])
            ->assertUnauthorized();
    }

    public function test_plan_checkout_returns_500_when_xendit_fails(): void
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/creator/plan/checkout', [
            'plan' => 'basic',
        ]);

        $response->assertStatus(500);
        $response->assertJsonStructure(['error']);
    }

    // ─── dashboard with analytics for creator plan user ──────────────────────

    public function test_dashboard_includes_analytics_for_basic_plan_user(): void
    {
        $owner = User::factory()->create();
        Community::factory()->create(['owner_id' => $owner->id, 'price' => 100]);

        \App\Models\CreatorSubscription::create([
            'user_id' => $owner->id,
            'plan' => 'basic',
            'status' => \App\Models\CreatorSubscription::STATUS_ACTIVE,
            'xendit_id' => 'test',
        ]);

        $mockAnalytics = $this->mock(CreatorAnalyticsService::class);
        $mockAnalytics->shouldReceive('build')
            ->with($owner->id)
            ->once()
            ->andReturn([
                'labels' => [],
                'revenue' => [],
                'newMembers' => [],
                'churn' => [],
                'retentionRate' => 100.0,
                'mrr' => 0,
            ]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('analytics')
            ->where('currentPlan', 'basic')
        );
    }

    public function test_dashboard_includes_analytics_for_pro_plan_user(): void
    {
        $owner = User::factory()->create();
        Community::factory()->create(['owner_id' => $owner->id, 'price' => 100]);

        \App\Models\CreatorSubscription::create([
            'user_id' => $owner->id,
            'plan' => 'pro',
            'status' => \App\Models\CreatorSubscription::STATUS_ACTIVE,
            'xendit_id' => 'test_pro',
        ]);

        $mockAnalytics = $this->mock(CreatorAnalyticsService::class);
        $mockAnalytics->shouldReceive('build')
            ->with($owner->id)
            ->once()
            ->andReturn([
                'labels' => [],
                'revenue' => [],
                'newMembers' => [],
                'churn' => [],
                'retentionRate' => 100.0,
                'mrr' => 0,
            ]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('analytics')
            ->where('currentPlan', 'pro')
        );
    }

    public function test_dashboard_does_not_include_analytics_for_free_plan_user(): void
    {
        $owner = User::factory()->create();
        Community::factory()->create(['owner_id' => $owner->id, 'price' => 100]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->where('analytics', null)
            ->where('currentPlan', 'free')
        );
    }

    // ─── redeemCoupon ─────────────────────────────────────────────────────────

    public function test_user_can_redeem_valid_coupon(): void
    {
        $user = User::factory()->create();

        $mockAction = $this->mock(\App\Actions\Coupon\RedeemCoupon::class);
        $fakeSub = \App\Models\CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => 'basic',
            'status' => \App\Models\CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(\Mockery::on(fn ($u) => $u->id === $user->id), 'TESTCODE')
            ->andReturn($fakeSub);

        $response = $this->actingAs($user)
            ->post('/creator/plan/redeem-coupon', ['code' => 'TESTCODE']);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_redeem_coupon_validates_code_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/creator/plan/redeem-coupon', [])
            ->assertSessionHasErrors('code');
    }

    public function test_redeem_coupon_validates_code_max_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/creator/plan/redeem-coupon', ['code' => str_repeat('X', 33)])
            ->assertSessionHasErrors('code');
    }

    public function test_redeem_coupon_returns_error_for_invalid_code(): void
    {
        $user = User::factory()->create();

        $mockAction = $this->mock(\App\Actions\Coupon\RedeemCoupon::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andThrow(new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'Invalid or expired coupon code.'));

        $response = $this->actingAs($user)
            ->post('/creator/plan/redeem-coupon', ['code' => 'BADCODE']);

        $response->assertRedirect();
        $response->assertSessionHasErrors('code');
    }

    public function test_guest_cannot_redeem_coupon(): void
    {
        $this->post('/creator/plan/redeem-coupon', ['code' => 'TEST'])
            ->assertRedirect('/login');
    }

    // ─── plan page auto-renew and recurring flags ────────────────────────────

    public function test_plan_page_shows_auto_renew_status(): void
    {
        $user = User::factory()->create();
        \App\Models\CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => 'basic',
            'status' => \App\Models\CreatorSubscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_test',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->get('/creator/plan');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Plan')
            ->where('isRecurring', true)
            ->where('isAutoRenewing', true)
            ->where('currentPlan', 'basic')
        );
    }

    // ─── dashboard handles exception gracefully ──────────────────────────────

    public function test_dashboard_handles_exception_gracefully(): void
    {
        $owner = User::factory()->create();
        Community::factory()->create(['owner_id' => $owner->id, 'price' => 100]);

        $this->mock(\App\Queries\Creator\GetCreatorDashboard::class, function ($mock) {
            $mock->shouldReceive('execute')->andThrow(new \RuntimeException('Database error'));
        });

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->where('communities', [])
            ->where('requestHistory', [])
            ->has('error')
        );
    }
}
