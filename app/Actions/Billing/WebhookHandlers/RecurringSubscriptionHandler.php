<?php

namespace App\Actions\Billing\WebhookHandlers;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Actions\Auth\IssueGuestPassword;
use App\Actions\Billing\SendChaChing;
use App\Actions\Billing\SyncMembershipFromSubscription;
use App\Events\SubscriptionPaid as SubscriptionPaidEvent;
use App\Models\Affiliate;
use App\Models\CartEvent;
use App\Models\CommunityMember;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\AffiliateCodeGenerator;
use App\Support\CacheKeys;
use Illuminate\Database\Eloquent\Model;

class RecurringSubscriptionHandler extends AbstractRecurringCycleHandler
{
    public function __construct(
        private readonly SyncMembershipFromSubscription $syncMembership,
        private readonly RecordAffiliateConversion $recordConversion,
        private readonly SendChaChing $sendChaChing,
        private readonly IssueGuestPassword $issueGuestPassword,
    ) {}

    protected function findEntityByPlanId(string $planId): ?Model
    {
        return Subscription::with('community')
            ->where('xendit_plan_id', $planId)
            ->first();
    }

    protected function activeStatus(): string
    {
        return Subscription::STATUS_ACTIVE;
    }

    protected function calculatePlatformFee(Model $entity, float $gross): float
    {
        return round($gross * $entity->community->platformFeeRate(), 2);
    }

    protected function subscriptionId(Model $entity): ?int
    {
        return $entity->id;
    }

    protected function communityId(Model $entity): ?int
    {
        return $entity->community_id;
    }

    protected function recordAffiliateCommission(Model $entity, Payment $payment, array $payload): ?array
    {
        if (! $entity->affiliate_id) {
            // No affiliate — still build cha-ching data for creator notification
            $community = $entity->community;

            return [
                'affiliate_user' => null,
                'creator'        => $community->owner,
                'community'      => $community,
                'sale_amount'    => (float) ($payload['amount'] ?? 0),
                'commission'     => null,
                'referred_by'    => null,
            ];
        }

        $this->recordConversion->execute($entity->load('affiliate.community'), $payment);

        $affiliate     = $entity->affiliate;
        $affiliateUser = $affiliate->user;
        $community     = $entity->community;
        $saleAmount    = (float) ($payload['amount'] ?? 0);
        $rate          = $community->affiliate_commission_rate / 100;
        $commission    = round($saleAmount * $rate, 2);

        return [
            'affiliate_user' => $affiliateUser,
            'creator'        => $community->owner,
            'community'      => $community,
            'sale_amount'    => $saleAmount,
            'commission'     => $commission,
            'referred_by'    => $affiliateUser->name,
        ];
    }

    protected function onPaymentSucceeded(Model $entity, array $payload): void
    {
        $community = $entity->community;

        // Auto-create affiliate/invite code for every paid subscriber
        if ($community) {
            $alreadyAffiliate = Affiliate::where('community_id', $community->id)
                ->where('user_id', $entity->user_id)
                ->exists();

            if (! $alreadyAffiliate) {
                Affiliate::create([
                    'community_id' => $community->id,
                    'user_id'      => $entity->user_id,
                    'code'         => AffiliateCodeGenerator::generate(),
                    'status'       => Affiliate::STATUS_ACTIVE,
                ]);
            }
        }

        // Reset renewal reminder timestamps (not needed for recurring, but keeps state clean)
        $entity->update([
            'reminder_5d_sent_at' => null,
            'reminder_1d_sent_at' => null,
        ]);

        $this->syncMembership->execute($entity->fresh());
    }

    protected function onPlanActivated(Model $entity, array $payload): void
    {
        // Generate guest password on first activation
        $user = $entity->user;
        $tempPassword = $this->issueGuestPassword->generate($user);

        if ($tempPassword && $entity->community) {
            $this->issueGuestPassword->sendEmail($user, $tempPassword, $entity->community);
        }

        $this->syncMembership->execute($entity->fresh());
    }

    protected function afterCommit(Model $entity, array $payload, ?array $sideEffects): void
    {
        // Flush cached analytics
        if ($entity->community) {
            CacheKeys::flushPayment($entity->community_id, $entity->community->owner_id);
        }

        // Send cha-ching email
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

        // Dispatch SubscriptionPaid event for email sequences
        if ($entity->isActive() && $entity->community) {
            $member = CommunityMember::where('community_id', $entity->community_id)
                ->where('user_id', $entity->user_id)
                ->first();

            if ($member) {
                SubscriptionPaidEvent::dispatch($member, $entity);
            }

            // Mark cart events as completed
            CartEvent::where('community_id', $entity->community_id)
                ->where('user_id', $entity->user_id)
                ->where('event_type', CartEvent::TYPE_CHECKOUT_STARTED)
                ->update(['event_type' => CartEvent::TYPE_PAYMENT_COMPLETED]);
        }
    }
}
