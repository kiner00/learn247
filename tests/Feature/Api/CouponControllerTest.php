<?php

namespace Tests\Feature\Api;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CreatorSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_redeem_valid_coupon(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::create([
            'code' => 'WELCOME10',
            'plan' => 'basic',
            'duration_months' => 3,
            'max_redemptions' => 100,
            'times_redeemed' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/coupons/WELCOME10/redeem');

        $response->assertOk()
            ->assertJsonPath('message', 'Coupon redeemed.')
            ->assertJsonPath('plan', 'basic')
            ->assertJsonStructure(['message', 'plan', 'expires_at']);

        $this->assertDatabaseHas('creator_subscriptions', [
            'user_id' => $user->id,
            'plan' => 'basic',
            'status' => CreatorSubscription::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
        ]);
        $this->assertEquals(1, $coupon->fresh()->times_redeemed);
    }

    public function test_code_is_normalized_case_insensitive(): void
    {
        $user = User::factory()->create();
        Coupon::create([
            'code' => 'HELLO',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 5,
            'times_redeemed' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/coupons/hello/redeem')
            ->assertOk();
    }

    public function test_invalid_code_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/coupons/NOPE/redeem')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid coupon code.');
    }

    public function test_inactive_coupon_is_rejected(): void
    {
        $user = User::factory()->create();
        Coupon::create([
            'code' => 'DISABLED',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 5,
            'times_redeemed' => 0,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->postJson('/api/coupons/DISABLED/redeem')
            ->assertStatus(422)
            ->assertJsonPath('message', 'This coupon is no longer available.');
    }

    public function test_user_cannot_redeem_same_coupon_twice(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::create([
            'code' => 'ONCE',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 5,
            'times_redeemed' => 1,
            'is_active' => true,
        ]);
        $sub = CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => 'basic',
            'status' => CreatorSubscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'creator_subscription_id' => $sub->id,
            'redeemed_at' => now()->subMonth(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/coupons/ONCE/redeem')
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already used this coupon.');
    }

    public function test_user_with_equal_or_higher_plan_is_rejected(): void
    {
        $user = User::factory()->create();
        CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonths(6),
        ]);
        Coupon::create([
            'code' => 'BASICONE',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 5,
            'times_redeemed' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/coupons/BASICONE/redeem')
            ->assertStatus(422)
            ->assertJsonPath('message', 'You already have an active pro plan.');
    }

    public function test_unauthenticated_cannot_redeem(): void
    {
        Coupon::create([
            'code' => 'GUEST',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 5,
            'times_redeemed' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/coupons/GUEST/redeem')
            ->assertUnauthorized();
    }
}
