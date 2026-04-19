<?php

namespace App\Actions\Billing;

use App\Models\CreatorSubscription;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use App\Support\RecurringPlanBuilder;
use Illuminate\Support\Facades\Log;

class EnableAutoRenew
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * Convert an existing invoice-based subscription to auto-renew.
     * Does NOT charge immediately — first charge happens at expires_at.
     *
     * @return string The card linking URL
     */
    public function executeForSubscription(Subscription $subscription): string
    {
        $this->validateNotAlreadyRecurring($subscription);

        $user = $subscription->user;
        $community = $subscription->community;

        $plan = $this->createPlan(
            user: $user,
            amount: (float) $community->price,
            currency: $community->currency ?? 'PHP',
            description: "Auto-renew: {$community->name}",
            referenceId: "{$community->slug}_autorenew_{$user->id}_".time(),
            anchorDate: $subscription->expires_at,
            successUrl: config('app.url')."/communities/{$community->slug}",
            failureUrl: config('app.url')."/communities/{$community->slug}",
        );

        $subscription->update([
            'xendit_plan_id' => $plan['id'],
            'xendit_customer_id' => $user->xendit_customer_id,
            'recurring_status' => $plan['status'] ?? 'REQUIRES_ACTION',
        ]);

        return $plan['actions'][0]['url'] ?? '';
    }

    /**
     * Convert an existing invoice-based creator plan to auto-renew.
     *
     * @return string The card linking URL
     */
    public function executeForCreatorPlan(CreatorSubscription $creatorSub): string
    {
        $this->validateNotAlreadyRecurring($creatorSub);

        $user = $creatorSub->user;
        $planLabel = $creatorSub->plan === CreatorSubscription::PLAN_PRO ? 'Pro' : 'Basic';
        $priceKey = $creatorSub->plan === CreatorSubscription::PLAN_PRO
            ? 'creator_plan_pro_price'
            : 'creator_plan_basic_price';
        $defaultPrice = $creatorSub->plan === CreatorSubscription::PLAN_PRO ? 1999 : 499;
        $price = (float) Setting::get($priceKey, $defaultPrice);

        $plan = $this->createPlan(
            user: $user,
            amount: $price,
            currency: 'PHP',
            description: "Auto-renew: Creator {$planLabel} Plan",
            referenceId: "creator_{$creatorSub->plan}_autorenew_{$user->id}_".time(),
            anchorDate: $creatorSub->expires_at,
            successUrl: config('app.url').'/creator/plan?autorenew=1',
            failureUrl: config('app.url').'/creator/plan?autorenew=failed',
        );

        $creatorSub->update([
            'xendit_plan_id' => $plan['id'],
            'xendit_customer_id' => $user->xendit_customer_id,
            'recurring_status' => $plan['status'] ?? 'REQUIRES_ACTION',
        ]);

        return $plan['actions'][0]['url'] ?? '';
    }

    private function createPlan(
        User $user,
        float $amount,
        string $currency,
        string $description,
        string $referenceId,
        ?\Carbon\Carbon $anchorDate,
        string $successUrl,
        string $failureUrl,
    ): array {
        $customerId = $user->ensureXenditCustomer($this->xendit);

        $builder = RecurringPlanBuilder::make()
            ->referenceId($referenceId)
            ->customerId($customerId)
            ->amount($amount)
            ->currency($currency)
            ->description($description)
            ->monthlyInterval()
            ->skipImmediateCharge()
            ->retryConfig(totalRetry: 3, intervalDays: 1)
            ->successReturnUrl($successUrl)
            ->failureReturnUrl($failureUrl);

        if ($anchorDate) {
            $builder->anchorDate($anchorDate);
        }

        try {
            return $this->xendit->createRecurringPlan($builder->toArray());
        } catch (\Throwable $e) {
            Log::error('EnableAutoRenew failed', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function validateNotAlreadyRecurring($entity): void
    {
        if ($entity->xendit_plan_id) {
            throw new \RuntimeException('This subscription already has auto-renew enabled.');
        }
    }
}
