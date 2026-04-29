<?php

namespace App\Actions\Billing\WebhookHandlers;

use App\Actions\CreatorPlanAffiliate\RecordCreatorPlanAffiliateConversion;
use App\Models\CreatorSubscription;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class RecurringCreatorPlanHandler extends AbstractRecurringCycleHandler
{
    protected function findEntityByPlanId(string $planId): ?Model
    {
        return CreatorSubscription::with('user')
            ->where('xendit_plan_id', $planId)
            ->first();
    }

    protected function activeStatus(): string
    {
        return CreatorSubscription::STATUS_ACTIVE;
    }

    protected function extendExpiry(Model $entity): void
    {
        /** @var CreatorSubscription $entity */
        $base = $entity->expires_at?->isFuture() ? $entity->expires_at : now();

        $entity->update([
            'expires_at' => $entity->isAnnual() ? $base->copy()->addYear() : $base->copy()->addMonth(),
        ]);
    }

    protected function recordAffiliateCommission(Model $entity, Payment $payment, array $payload): ?array
    {
        /** @var CreatorSubscription $entity */
        return app(RecordCreatorPlanAffiliateConversion::class)->execute($entity, $payment);
    }
}
