<?php

namespace App\Actions\Billing;

use App\Billing\CheckoutContext;
use App\Billing\Strategies\RecurringCheckoutStrategy;
use App\Models\CreatorSubscription;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StartCreatorPlanCheckout
{
    public function __construct(private readonly RecurringCheckoutStrategy $strategy) {}

    /**
     * @return array{creator_subscription: CreatorSubscription, checkout_url: string}
     * @throws ValidationException|\RuntimeException
     */
    public function execute(User $user, string $plan): array
    {
        if (! in_array($plan, [CreatorSubscription::PLAN_BASIC, CreatorSubscription::PLAN_PRO])) {
            throw ValidationException::withMessages(['plan' => 'Invalid plan selected.']);
        }

        $currentPlan = $user->creatorPlan();
        if ($currentPlan === $plan) {
            throw ValidationException::withMessages(['plan' => 'You already have this plan active.']);
        }

        $priceKey = $plan === CreatorSubscription::PLAN_PRO
            ? 'creator_plan_pro_price'
            : 'creator_plan_basic_price';

        $defaultPrice = $plan === CreatorSubscription::PLAN_PRO ? 1999 : 499;
        $price        = (float) Setting::get($priceKey, $defaultPrice);

        $planLabel  = $plan === CreatorSubscription::PLAN_PRO ? 'Pro' : 'Basic';

        try {
            // Creator plans are always monthly — always use recurring strategy
            $result = $this->strategy->initiatePayment(new CheckoutContext(
                user: $user,
                amount: $price,
                currency: 'PHP',
                description: "Creator {$planLabel} Plan — Monthly",
                referenceId: "creator_plan_{$plan}_{$user->id}_" . time(),
                successUrl: config('app.url') . '/creator/plan?success=1',
                failureUrl: config('app.url') . '/creator/plan?failed=1',
                itemName: "Creator {$planLabel} Plan",
                itemCategory: 'Creator Subscription',
            ));

            $creatorSubscription = CreatorSubscription::create([
                'user_id'            => $user->id,
                'plan'               => $plan,
                'status'             => CreatorSubscription::STATUS_PENDING,
                'xendit_id'          => $result->invoiceId,
                'xendit_invoice_url' => $result->invoiceUrl,
                'xendit_plan_id'     => $result->planId,
                'xendit_customer_id' => $result->customerId,
                'recurring_status'   => $result->recurringStatus,
            ]);

            return [
                'creator_subscription' => $creatorSubscription,
                'checkout_url'         => $result->checkoutUrl,
            ];
        } catch (\Throwable $e) {
            Log::error('StartCreatorPlanCheckout failed', [
                'user_id' => $user->id,
                'plan'    => $plan,
                'error'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
