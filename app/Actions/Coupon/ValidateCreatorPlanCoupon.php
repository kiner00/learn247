<?php

namespace App\Actions\Coupon;

use App\Models\Coupon;
use App\Models\CreatorSubscription;
use App\Models\User;
use App\Support\CreatorPlanPricing;
use Illuminate\Validation\ValidationException;

class ValidateCreatorPlanCoupon
{
    /**
     * Validate a discount-type coupon against a plan + cycle and return the pricing preview.
     * Does NOT consume the coupon — that happens on successful payment (webhook).
     *
     * @return array{
     *     coupon: Coupon,
     *     plan: string,
     *     cycle: string,
     *     original_price: float,
     *     discounted_price: float,
     *     discount_percent: float,
     *     savings: float,
     * }
     *
     * @throws ValidationException
     */
    public function execute(User $user, string $code, string $plan, string $cycle): array
    {
        if (! in_array($plan, [CreatorSubscription::PLAN_BASIC, CreatorSubscription::PLAN_PRO])) {
            throw ValidationException::withMessages(['plan' => 'Invalid plan.']);
        }
        if (! in_array($cycle, [CreatorSubscription::CYCLE_MONTHLY, CreatorSubscription::CYCLE_ANNUAL])) {
            throw ValidationException::withMessages(['cycle' => 'Invalid billing cycle.']);
        }

        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['code' => 'Invalid coupon code.']);
        }
        if (! $coupon->isDiscount()) {
            throw ValidationException::withMessages(['code' => 'This coupon cannot be used at checkout.']);
        }
        if (! $coupon->isRedeemable()) {
            throw ValidationException::withMessages(['code' => 'This coupon is no longer available.']);
        }
        if ($coupon->hasBeenRedeemedBy($user->id)) {
            throw ValidationException::withMessages(['code' => 'You have already used this coupon.']);
        }
        if (! $coupon->appliesToPlan($plan)) {
            throw ValidationException::withMessages(['code' => "This coupon doesn't apply to the {$plan} plan."]);
        }
        if (! $coupon->appliesToCycle($cycle)) {
            throw ValidationException::withMessages(['code' => "This coupon doesn't apply to {$cycle} billing."]);
        }

        $originalPrice = CreatorPlanPricing::priceFor($plan, $cycle);
        $discountedPrice = $coupon->computeDiscountedPrice($plan, $cycle);

        return [
            'coupon' => $coupon,
            'plan' => $plan,
            'cycle' => $cycle,
            'original_price' => $originalPrice,
            'discounted_price' => $discountedPrice,
            'discount_percent' => (float) $coupon->discount_percent,
            'savings' => round($originalPrice - $discountedPrice, 2),
        ];
    }
}
