<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Creator\GetCreatorDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── dashboard ────────────────────────────────────────────────────────────

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/creator/dashboard')
            ->assertUnauthorized();
    }

    public function test_dashboard_returns_empty_when_owner_has_no_paid_communities(): void
    {
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/creator/dashboard')
            ->assertOk()
            ->assertJsonStructure(['communities', 'requestHistory', 'payoutMethod', 'payoutDetails'])
            ->assertJsonCount(0, 'communities');
    }

    public function test_dashboard_returns_community_revenue_data(): void
    {
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 499]);

        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        Payment::create([
            'subscription_id' => $subscription->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 499,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'provider_reference' => 'pay_test',
            'paid_at' => now()->subDays(20),
        ]);

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/creator/dashboard');

        $response->assertOk()
            ->assertJsonCount(1, 'communities')
            ->assertJsonStructure([
                'communities' => [[
                    'community_id',
                    'community_name',
                    'community_slug',
                    'members_count',
                    'gross',
                    'platform_fee',
                    'commissions',
                    'earned',
                    'paid',
                    'eligible_now',
                    'locked_amount',
                    'next_eligible_date',
                    'pending_request',
                    'recent_payments',
                ]],
                'requestHistory',
                'payoutMethod',
                'payoutDetails',
            ]);

        $data = $response->json('communities.0');
        $this->assertEquals($community->id, $data['community_id']);
        $this->assertEquals(499, $data['gross']);
        $this->assertEquals(round(499 * 0.098, 2), $data['platform_fee']); // 9.8% (free plan)
    }

    public function test_dashboard_includes_payout_request_history(): void
    {
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 499]);

        PayoutRequest::create([
            'user_id' => $owner->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'community_id' => $community->id,
            'amount' => 100,
            'eligible_amount' => 200,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/creator/dashboard');

        $response->assertOk()
            ->assertJsonCount(1, 'requestHistory');
    }

    public function test_dashboard_shows_correct_paid_amount(): void
    {
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 499]);

        $subscriber = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Payment::create([
            'subscription_id' => $subscription->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 499,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'provider_reference' => 'pay_test',
            'paid_at' => now()->subDays(20),
        ]);

        OwnerPayout::create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'amount' => 100,
            'status' => 'completed',
        ]);

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/creator/dashboard');

        $response->assertOk();
        $this->assertEquals(100, $response->json('communities.0.paid'));
    }

    public function test_dashboard_excludes_free_communities(): void
    {
        $owner = User::factory()->create();
        Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/creator/dashboard')
            ->assertOk()
            ->assertJsonCount(0, 'communities');
    }

    public function test_dashboard_returns_500_when_query_throws(): void
    {
        $owner = User::factory()->create();

        $mock = $this->mock(GetCreatorDashboard::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('DB error'));

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/creator/dashboard')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to load dashboard data.');
    }

    // ─── plan (subscription tier) ──────────────────────────────────────────

    public function test_plan_endpoint_returns_pricing_and_state(): void
    {
        \App\Models\Setting::set('creator_plan_basic_price', 499);
        \App\Models\Setting::set('creator_plan_pro_price', 1999);
        \App\Models\Setting::set('creator_plan_basic_annual_price', 4990);
        \App\Models\Setting::set('creator_plan_pro_annual_price', 19990);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/creator/plan')
            ->assertOk()
            ->assertJson([
                'pricing' => [
                    'basic_monthly' => 499,
                    'pro_monthly' => 1999,
                    'basic_annual' => 4990,
                    'pro_annual' => 19990,
                    'currency' => 'PHP',
                ],
                'current_plan' => 'free',
                'current_cycle' => 'monthly',
            ]);
    }

    public function test_validate_coupon_endpoint_returns_preview(): void
    {
        \App\Models\Setting::set('creator_plan_pro_price', 1999);
        \App\Models\Coupon::create([
            'code' => 'SAVE30',
            'type' => 'discount',
            'plan' => 'pro',
            'applies_to' => 'annual',
            'discount_percent' => 30,
            'max_redemptions' => 10,
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/creator/plan/validate-coupon', [
                'code' => 'SAVE30', 'plan' => 'pro', 'cycle' => 'annual',
            ])
            ->assertOk()
            ->assertJson(['code' => 'SAVE30', 'discount_percent' => 30]);
    }

    public function test_api_checkout_accepts_coupon_code(): void
    {
        \App\Models\Setting::set('creator_plan_pro_price', 1999);
        $coupon = \App\Models\Coupon::create([
            'code' => 'API30',
            'type' => 'discount',
            'plan' => 'pro',
            'applies_to' => 'annual',
            'discount_percent' => 30,
            'max_redemptions' => 10,
            'is_active' => true,
        ]);

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'inv_api_1',
                'invoice_url' => 'https://checkout.xendit.co/inv_api_1',
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/creator/plan/checkout', [
                'plan' => 'pro', 'cycle' => 'annual', 'coupon_code' => 'API30',
            ])
            ->assertOk()
            ->assertJsonStructure(['checkout_url', 'subscription_id']);

        $this->assertDatabaseHas('creator_subscriptions', [
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
        ]);
    }
}
