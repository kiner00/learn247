<?php

namespace App\Actions\Affiliate;

use App\Contracts\BadgeEvaluator;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\CertificationPurchase;
use App\Models\Community;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Affiliate\AttributionResolver;
use App\Services\Wallet\WalletService;
use App\Support\AffiliateSubscriptionChecker;
use Illuminate\Support\Facades\Log;

class RecordAffiliateConversion
{
    public function __construct(
        private BadgeEvaluator $badges,
        private AttributionResolver $resolver,
        private WalletService $wallet,
    ) {}

    public function execute(Subscription $subscription, Payment $payment): void
    {
        $this->record(
            community: $subscription->community,
            lastTouch: $subscription->affiliate,
            referredUserId: $subscription->user_id,
            rate: $subscription->community->affiliate_commission_rate / 100,
            saleAmount: (float) $payment->amount,
            extraData: [
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'referred_user_id' => $subscription->user_id,
            ],
            logPrefix: 'Affiliate commission skipped',
        );
    }

    /**
     * @return array{commission: float, sale_amount: float}|null
     */
    public function executeForCourse(CourseEnrollment $enrollment): ?array
    {
        $course = $enrollment->course;
        $rate = ($course->affiliate_commission_rate ?? 0) / 100;

        if ($rate <= 0) {
            Log::info('Course affiliate commission skipped — no commission rate set', ['course_id' => $course->id]);

            return null;
        }

        return $this->record(
            community: $course->community,
            lastTouch: $enrollment->affiliate,
            referredUserId: $enrollment->user_id,
            rate: $rate,
            saleAmount: (float) $course->price,
            extraData: [
                'course_enrollment_id' => $enrollment->id,
                'referred_user_id' => $enrollment->user_id,
            ],
            logPrefix: 'Course affiliate commission skipped',
        );
    }

    /**
     * @return array{commission: float, sale_amount: float}|null
     */
    public function executeForCertification(CertificationPurchase $purchase): ?array
    {
        $certification = $purchase->certification;
        $rate = ($certification->affiliate_commission_rate ?? 0) / 100;

        if ($rate <= 0) {
            Log::info('Certification affiliate commission skipped — no commission rate set', ['certification_id' => $certification->id]);

            return null;
        }

        if (AffiliateConversion::where('certification_purchase_id', $purchase->id)->exists()) {
            Log::info('Certification affiliate commission skipped — already recorded', ['purchase_id' => $purchase->id]);

            return null;
        }

        return $this->record(
            community: $certification->community,
            lastTouch: $purchase->affiliate,
            referredUserId: $purchase->user_id,
            rate: $rate,
            saleAmount: (float) $certification->price,
            extraData: [
                'certification_purchase_id' => $purchase->id,
                'referred_user_id' => $purchase->user_id,
            ],
            logPrefix: 'Certification affiliate commission skipped',
        );
    }

    /**
     * @return array{commission: float, sale_amount: float}|null
     */
    public function executeForCurzzo(\App\Models\CurzzoPurchase $purchase): ?array
    {
        $curzzo = $purchase->curzzo;
        $rate = ($curzzo->affiliate_commission_rate ?? 0) / 100;

        if ($rate <= 0) {
            Log::info('Curzzo affiliate commission skipped — no commission rate set', ['curzzo_id' => $curzzo->id]);

            return null;
        }

        if (AffiliateConversion::where('curzzo_purchase_id', $purchase->id)->exists()) {
            Log::info('Curzzo affiliate commission skipped — already recorded', ['purchase_id' => $purchase->id]);

            return null;
        }

        return $this->record(
            community: $curzzo->community,
            lastTouch: $purchase->affiliate,
            referredUserId: $purchase->user_id,
            rate: $rate,
            saleAmount: (float) $curzzo->price,
            extraData: [
                'curzzo_purchase_id' => $purchase->id,
                'referred_user_id' => $purchase->user_id,
            ],
            logPrefix: 'Curzzo affiliate commission skipped',
        );
    }

    /**
     * @return array{commission: float, sale_amount: float}|null
     */
    private function record(
        ?Community $community,
        ?Affiliate $lastTouch,
        ?int $referredUserId,
        float $rate,
        float $saleAmount,
        array $extraData,
        string $logPrefix,
    ): ?array {
        if (! $community) {
            return null;
        }

        $resolution = $this->resolver->resolve($community, $referredUserId, $lastTouch);
        $affiliate = $resolution['affiliate'];

        if (! $affiliate) {
            return null;
        }

        if (! AffiliateSubscriptionChecker::isActivelySubscribed($affiliate->user_id, $affiliate->community_id)) {
            Log::info("{$logPrefix} — affiliate not subscribed", ['affiliate_id' => $affiliate->id]);

            return null;
        }

        $platformFee = round($saleAmount * $community->platformFeeRate(), 2);
        $commission = round($saleAmount * $rate, 2);
        $creatorAmount = round($saleAmount - $platformFee - $commission, 2);

        $conversion = AffiliateConversion::create(array_merge($extraData, [
            'affiliate_id' => $affiliate->id,
            'sale_amount' => $saleAmount,
            'platform_fee' => $platformFee,
            'commission_amount' => $commission,
            'creator_amount' => $creatorAmount,
            'status' => AffiliateConversion::STATUS_PENDING,
            'is_lifetime' => $resolution['is_lifetime'],
        ]));

        $affiliate->increment('total_earned', $commission);

        $affiliateUser = User::find($affiliate->user_id);
        if ($affiliateUser) {
            $this->wallet->credit(
                user: $affiliateUser,
                source: $conversion,
                amount: $commission,
                status: WalletTransaction::STATUS_PAID,
                availableAt: now()->addDays(config('affiliate.hold_days', 7)),
                opts: [
                    'description' => "Affiliate commission — {$community->name}",
                    'metadata' => ['is_lifetime' => $resolution['is_lifetime']],
                ],
            );

            $this->badges->evaluate($affiliateUser);
        }

        return ['commission' => $commission, 'sale_amount' => $saleAmount];
    }
}
