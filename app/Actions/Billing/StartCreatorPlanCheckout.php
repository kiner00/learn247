<?php

namespace App\Actions\Billing;

use App\Actions\Coupon\ValidateCreatorPlanCoupon;
use App\Billing\CheckoutContext;
use App\Billing\CheckoutStrategyFactory;
use App\Models\CreatorSubscription;
use App\Models\User;
use App\Support\CreatorPlanPricing;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StartCreatorPlanCheckout
{
    public function __construct(private readonly ValidateCreatorPlanCoupon $validateCoupon) {}

    /**
     * @return array{creator_subscription: CreatorSubscription, checkout_url: string}
     *
     * @throws ValidationException|\RuntimeException
     */
    public function execute(
        User $user,
        string $plan,
        string $cycle = CreatorSubscription::CYCLE_MONTHLY,
        ?string $couponCode = null,
    ): array {
        if (! in_array($plan, [CreatorSubscription::PLAN_BASIC, CreatorSubscription::PLAN_PRO])) {
            throw ValidationException::withMessages(['plan' => 'Invalid plan selected.']);
        }

        if (! in_array($cycle, [CreatorSubscription::CYCLE_MONTHLY, CreatorSubscription::CYCLE_ANNUAL])) {
            throw ValidationException::withMessages(['cycle' => 'Invalid billing cycle selected.']);
        }

        $currentPlan = $user->creatorPlan();
        if ($currentPlan === $plan) {
            throw ValidationException::withMessages(['plan' => 'You already have this plan active.']);
        }

        $coupon = null;
        $price = CreatorPlanPricing::priceFor($plan, $cycle);

        if ($couponCode !== null && trim($couponCode) !== '') {
            $validation = $this->validateCoupon->execute($user, $couponCode, $plan, $cycle);
            $coupon = $validation['coupon'];
            $price = $validation['discounted_price'];
        }

        $planLabel = $plan === CreatorSubscription::PLAN_PRO ? 'Pro' : 'Basic';
        $cycleLabel = $cycle === CreatorSubscription::CYCLE_ANNUAL ? 'Annual' : 'Monthly';
        $description = "Creator {$planLabel} Plan — {$cycleLabel}";
        if ($coupon) {
            $description .= " (coupon {$coupon->code})";
        }

        try {
            $strategy = CheckoutStrategyFactory::make('monthly');
            $result = $strategy->initiatePayment(new CheckoutContext(
                user: $user,
                amount: $price,
                currency: 'PHP',
                description: $description,
                referenceId: "creator_plan_{$plan}_{$cycle}_{$user->id}_".time(),
                successUrl: config('app.url').'/creator/plan?success=1',
                failureUrl: config('app.url').'/creator/plan?failed=1',
                itemName: "Creator {$planLabel} Plan",
                itemCategory: 'Creator Subscription',
            ));

            $creatorSubscription = CreatorSubscription::create([
                'user_id' => $user->id,
                'plan' => $plan,
                'billing_cycle' => $cycle,
                'coupon_id' => $coupon?->id,
                'status' => CreatorSubscription::STATUS_PENDING,
                'xendit_id' => $result->invoiceId,
                'xendit_invoice_url' => $result->invoiceUrl,
                'xendit_plan_id' => $result->planId,
                'xendit_customer_id' => $result->customerId,
                'recurring_status' => $result->recurringStatus,
            ]);

            return [
                'creator_subscription' => $creatorSubscription,
                'checkout_url' => $result->checkoutUrl,
            ];
        } catch (\Throwable $e) {
            Log::error('StartCreatorPlanCheckout failed', [
                'user_id' => $user->id,
                'plan' => $plan,
                'cycle' => $cycle,
                'coupon_code' => $coupon?->code,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
