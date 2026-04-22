<?php

namespace Tests\Feature\Actions\Coupon;

use App\Actions\Coupon\ValidateCreatorPlanCoupon;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CreatorSubscription;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ValidateCreatorPlanCouponTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('creator_plan_basic_price', 499);
        Setting::set('creator_plan_pro_price', 1999);
    }

    private function discount(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE30',
            'type' => Coupon::TYPE_DISCOUNT,
            'plan' => 'pro',
            'applies_to' => Coupon::APPLIES_TO_ANNUAL,
            'discount_percent' => 30,
            'max_redemptions' => 10,
            'is_active' => true,
        ], $overrides));
    }

    public function test_valid_annual_coupon_returns_preview_with_reduced_price(): void
    {
        // Annual sticker price = 19990 (2 months free baked in)
        Setting::set('creator_plan_pro_annual_price', 19990);
        $user = User::factory()->create();
        $this->discount();

        $result = app(ValidateCreatorPlanCoupon::class)
            ->execute($user, 'SAVE30', 'pro', 'annual');

        // Coupon discount baseline = monthly × 12 = 23988, 30% off → 16791.60
        // original_price is the annual sticker the user sees (19990)
        $this->assertSame('SAVE30', $result['coupon']->code);
        $this->assertEqualsWithDelta(19990.0, $result['original_price'], 0.01);
        $this->assertEqualsWithDelta(16791.6, $result['discounted_price'], 0.01);
        $this->assertEqualsWithDelta(30.0, $result['discount_percent'], 0.01);
        $this->assertEqualsWithDelta(3198.4, $result['savings'], 0.01);
    }

    public function test_code_is_uppercased_and_trimmed(): void
    {
        $user = User::factory()->create();
        $this->discount(['code' => 'SAVE30']);

        $result = app(ValidateCreatorPlanCoupon::class)
            ->execute($user, '  save30  ', 'pro', 'annual');

        $this->assertSame('SAVE30', $result['coupon']->code);
    }

    public function test_invalid_code_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        app(ValidateCreatorPlanCoupon::class)->execute($user, 'NOPE', 'pro', 'annual');
    }

    public function test_plan_grant_coupon_rejected_at_checkout(): void
    {
        $user = User::factory()->create();
        Coupon::create([
            'code' => 'GRANT1',
            'type' => Coupon::TYPE_PLAN_GRANT,
            'plan' => 'pro',
            'duration_months' => 1,
            'max_redemptions' => 5,
        ]);

        try {
            app(ValidateCreatorPlanCoupon::class)->execute($user, 'GRANT1', 'pro', 'annual');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }
    }

    public function test_coupon_for_wrong_plan_rejected(): void
    {
        $user = User::factory()->create();
        $this->discount(['plan' => 'basic']);

        $this->expectException(ValidationException::class);
        app(ValidateCreatorPlanCoupon::class)->execute($user, 'SAVE30', 'pro', 'annual');
    }

    public function test_coupon_for_wrong_cycle_rejected(): void
    {
        $user = User::factory()->create();
        $this->discount(['applies_to' => Coupon::APPLIES_TO_ANNUAL]);

        $this->expectException(ValidationException::class);
        app(ValidateCreatorPlanCoupon::class)->execute($user, 'SAVE30', 'pro', 'monthly');
    }

    public function test_plan_both_coupon_works_for_any_plan(): void
    {
        $user = User::factory()->create();
        $this->discount(['plan' => Coupon::PLAN_BOTH, 'applies_to' => Coupon::APPLIES_TO_BOTH]);

        // Basic/monthly should work
        $result = app(ValidateCreatorPlanCoupon::class)->execute($user, 'SAVE30', 'basic', 'monthly');
        $this->assertEqualsWithDelta(499 * 0.70, $result['discounted_price'], 0.01);

        // Pro/annual should also work
        $result = app(ValidateCreatorPlanCoupon::class)->execute($user, 'SAVE30', 'pro', 'annual');
        $this->assertEqualsWithDelta(23988 * 0.70, $result['discounted_price'], 0.01);
    }

    public function test_inactive_coupon_rejected(): void
    {
        $user = User::factory()->create();
        $this->discount(['is_active' => false]);

        $this->expectException(ValidationException::class);
        app(ValidateCreatorPlanCoupon::class)->execute($user, 'SAVE30', 'pro', 'annual');
    }

    public function test_exhausted_coupon_rejected(): void
    {
        $user = User::factory()->create();
        $this->discount(['max_redemptions' => 5, 'times_redeemed' => 5]);

        $this->expectException(ValidationException::class);
        app(ValidateCreatorPlanCoupon::class)->execute($user, 'SAVE30', 'pro', 'annual');
    }

    public function test_expired_coupon_rejected(): void
    {
        $user = User::factory()->create();
        $this->discount(['expires_at' => now()->subDay()]);

        $this->expectException(ValidationException::class);
        app(ValidateCreatorPlanCoupon::class)->execute($user, 'SAVE30', 'pro', 'annual');
    }

    public function test_already_redeemed_by_user_rejected(): void
    {
        $user = User::factory()->create();
        $coupon = $this->discount();

        $sub = CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'status' => CreatorSubscription::STATUS_ACTIVE,
        ]);
        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'creator_subscription_id' => $sub->id,
            'redeemed_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(ValidateCreatorPlanCoupon::class)->execute($user, 'SAVE30', 'pro', 'annual');
    }

    public function test_invalid_plan_arg_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        app(ValidateCreatorPlanCoupon::class)->execute($user, 'SAVE30', 'enterprise', 'annual');
    }

    public function test_invalid_cycle_arg_rejected(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        app(ValidateCreatorPlanCoupon::class)->execute($user, 'SAVE30', 'pro', 'weekly');
    }
}
