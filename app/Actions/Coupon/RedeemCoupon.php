<?php

namespace App\Actions\Coupon;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CreatorSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RedeemCoupon
{
    public function execute(User $user, string $code): CreatorSubscription
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        abort_unless($coupon, 422, 'Invalid coupon code.');
        abort_unless($coupon->isRedeemable(), 422, 'This coupon is no longer available.');
        abort_if($coupon->hasBeenRedeemedBy($user->id), 422, 'You have already used this coupon.');

        // If user already has an active plan at this level or higher, block
        $currentPlan = $user->creatorPlan();
        $planRank = ['free' => 0, 'basic' => 1, 'pro' => 2];

        abort_if(
            ($planRank[$currentPlan] ?? 0) >= ($planRank[$coupon->plan] ?? 0),
            422,
            "You already have an active {$currentPlan} plan."
        );

        return DB::transaction(function () use ($user, $coupon) {
            $subscription = CreatorSubscription::create([
                'user_id' => $user->id,
                'plan' => $coupon->plan,
                'status' => CreatorSubscription::STATUS_ACTIVE,
                'expires_at' => now()->addMonths($coupon->duration_months),
            ]);

            $coupon->increment('times_redeemed');

            CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'user_id' => $user->id,
                'creator_subscription_id' => $subscription->id,
                'redeemed_at' => now(),
            ]);

            return $subscription;
        });
    }
}
