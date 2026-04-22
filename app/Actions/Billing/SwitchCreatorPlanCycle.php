<?php

namespace App\Actions\Billing;

use App\Models\CreatorSubscription;
use App\Services\XenditService;
use App\Support\CreatorPlanPricing;
use App\Support\RecurringPlanBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SwitchCreatorPlanCycle
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * Switch an active creator subscription between monthly and annual billing.
     * Current period remains honoured — the new cycle takes effect on the next charge
     * (anchored at expires_at). If the sub has an auto-renew plan, it is cancelled
     * and re-created with the new interval; otherwise only billing_cycle is updated.
     *
     * @return array{creator_subscription: CreatorSubscription, linking_url: ?string}
     *
     * @throws ValidationException
     */
    public function execute(CreatorSubscription $creatorSub, string $newCycle): array
    {
        if (! in_array($newCycle, [CreatorSubscription::CYCLE_MONTHLY, CreatorSubscription::CYCLE_ANNUAL])) {
            throw ValidationException::withMessages(['cycle' => 'Invalid billing cycle.']);
        }

        if ($creatorSub->billing_cycle === $newCycle) {
            throw ValidationException::withMessages(['cycle' => 'Already on this billing cycle.']);
        }

        if ($creatorSub->status !== CreatorSubscription::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['cycle' => 'Only active subscriptions can change billing cycle.']);
        }

        $linkingUrl = null;

        if ($creatorSub->xendit_plan_id && $creatorSub->recurring_status === 'ACTIVE') {
            $linkingUrl = $this->swapRecurringPlan($creatorSub, $newCycle);
        } else {
            $creatorSub->update(['billing_cycle' => $newCycle]);
        }

        Log::info('Creator plan billing cycle switched', [
            'creator_sub_id' => $creatorSub->id,
            'from' => $creatorSub->getOriginal('billing_cycle'),
            'to' => $newCycle,
        ]);

        return [
            'creator_subscription' => $creatorSub->fresh(),
            'linking_url' => $linkingUrl,
        ];
    }

    private function swapRecurringPlan(CreatorSubscription $creatorSub, string $newCycle): ?string
    {
        try {
            $this->xendit->deactivateRecurringPlan($creatorSub->xendit_plan_id);
        } catch (\Throwable $e) {
            Log::error('SwitchCreatorPlanCycle: deactivate failed', [
                'creator_sub_id' => $creatorSub->id,
                'plan_id' => $creatorSub->xendit_plan_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $user = $creatorSub->user;
        $planLabel = $creatorSub->plan === CreatorSubscription::PLAN_PRO ? 'Pro' : 'Basic';
        $cycleLabel = $newCycle === CreatorSubscription::CYCLE_ANNUAL ? 'Annual' : 'Monthly';
        $price = CreatorPlanPricing::priceFor($creatorSub->plan, $newCycle);

        $customerId = $user->ensureXenditCustomer($this->xendit);

        $builder = RecurringPlanBuilder::make()
            ->referenceId("creator_{$creatorSub->plan}_{$newCycle}_switch_{$user->id}_".time())
            ->customerId($customerId)
            ->amount($price)
            ->currency('PHP')
            ->description("Auto-renew: Creator {$planLabel} Plan — {$cycleLabel}")
            ->skipImmediateCharge()
            ->retryConfig(totalRetry: 3, intervalDays: 1)
            ->successReturnUrl(config('app.url').'/creator/plan?autorenew=1')
            ->failureReturnUrl(config('app.url').'/creator/plan?autorenew=failed');

        $newCycle === CreatorSubscription::CYCLE_ANNUAL
            ? $builder->yearlyInterval()
            : $builder->monthlyInterval();

        if ($creatorSub->expires_at) {
            $builder->anchorDate($creatorSub->expires_at);
        }

        $plan = $this->xendit->createRecurringPlan($builder->toArray());

        $creatorSub->update([
            'billing_cycle' => $newCycle,
            'xendit_plan_id' => $plan['id'],
            'xendit_customer_id' => $user->xendit_customer_id,
            'recurring_status' => $plan['status'] ?? 'REQUIRES_ACTION',
        ]);

        return $plan['actions'][0]['url'] ?? null;
    }
}
