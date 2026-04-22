<?php

namespace App\Actions\Billing;

use App\Billing\CheckoutContext;
use App\Billing\CheckoutStrategyFactory;
use App\Models\CreatorSubscription;
use App\Models\User;
use App\Support\CreatorPlanPricing;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StartCreatorPlanCheckout
{
    /**
     * @return array{creator_subscription: CreatorSubscription, checkout_url: string}
     *
     * @throws ValidationException|\RuntimeException
     */
    public function execute(User $user, string $plan, string $cycle = CreatorSubscription::CYCLE_MONTHLY): array
    {
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

        $price = CreatorPlanPricing::priceFor($plan, $cycle);

        $planLabel = $plan === CreatorSubscription::PLAN_PRO ? 'Pro' : 'Basic';
        $cycleLabel = $cycle === CreatorSubscription::CYCLE_ANNUAL ? 'Annual' : 'Monthly';

        try {
            $strategy = CheckoutStrategyFactory::make('monthly');
            $result = $strategy->initiatePayment(new CheckoutContext(
                user: $user,
                amount: $price,
                currency: 'PHP',
                description: "Creator {$planLabel} Plan — {$cycleLabel}",
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
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
