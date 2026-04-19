<?php

namespace App\Actions\Billing\WebhookHandlers;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Actions\Billing\SendChaChing;
use App\Models\CurzzoPurchase;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class RecurringCurzzoPurchaseHandler extends AbstractRecurringCycleHandler
{
    public function __construct(
        private readonly RecordAffiliateConversion $recordConversion,
        private readonly SendChaChing $sendChaChing,
    ) {}

    protected function findEntityByPlanId(string $planId): ?Model
    {
        return CurzzoPurchase::with(['curzzo.community.owner', 'affiliate.user'])
            ->where('xendit_plan_id', $planId)
            ->first();
    }

    protected function activeStatus(): string
    {
        return CurzzoPurchase::STATUS_PAID;
    }

    protected function calculatePlatformFee(Model $entity, float $gross): float
    {
        $community = $entity->curzzo?->community;

        return $community
            ? round($gross * $community->platformFeeRate(), 2)
            : 0;
    }

    protected function communityId(Model $entity): ?int
    {
        return $entity->curzzo?->community_id;
    }

    protected function recordAffiliateCommission(Model $entity, Payment $payment, array $payload): ?array
    {
        $conversion = $this->recordConversion->executeForCurzzo($entity);

        if (! $conversion) {
            return null;
        }

        return [
            'affiliate_user' => $entity->affiliate->user,
            'creator' => $entity->curzzo->community->owner,
            'community' => $entity->curzzo->community,
            'sale_amount' => $conversion['sale_amount'],
            'commission' => $conversion['commission'],
            'referred_by' => $entity->affiliate->user->name,
        ];
    }

    protected function afterCommit(Model $entity, array $payload, ?array $sideEffects): void
    {
        if ($sideEffects) {
            $this->sendChaChing->execute(
                affiliateUser: $sideEffects['affiliate_user'],
                creator: $sideEffects['creator'],
                community: $sideEffects['community'],
                saleAmount: $sideEffects['sale_amount'],
                commission: $sideEffects['commission'],
                referredBy: $sideEffects['referred_by'],
            );
        }
    }
}
