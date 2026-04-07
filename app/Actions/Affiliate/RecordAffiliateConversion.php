<?php

namespace App\Actions\Affiliate;

use App\Contracts\BadgeEvaluator;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\CertificationPurchase;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AffiliateSubscriptionChecker;
use Illuminate\Support\Facades\Log;

class RecordAffiliateConversion
{
    public function __construct(private BadgeEvaluator $badges) {}

    public function execute(Subscription $subscription, Payment $payment): void
    {
        $affiliate = $subscription->affiliate;

        if (! $affiliate) {
            return;
        }

        $rate = $affiliate->community->affiliate_commission_rate / 100;

        $this->record($affiliate, $rate, (float) $payment->amount, [
            'subscription_id'  => $subscription->id,
            'payment_id'       => $payment->id,
            'referred_user_id' => $subscription->user_id,
        ], 'Affiliate commission skipped');
    }

    /**
     * @return array{commission: float, sale_amount: float}|null
     */
    public function executeForCourse(CourseEnrollment $enrollment): ?array
    {
        $affiliate = $enrollment->affiliate;

        if (! $affiliate) {
            return null;
        }

        $course = $enrollment->course;
        $rate   = ($course->affiliate_commission_rate ?? 0) / 100;

        if ($rate <= 0) {
            Log::info('Course affiliate commission skipped — no commission rate set', ['course_id' => $course->id]);
            return null;
        }

        return $this->record($affiliate, $rate, (float) $course->price, [
            'course_enrollment_id' => $enrollment->id,
            'referred_user_id'     => $enrollment->user_id,
        ], 'Course affiliate commission skipped');
    }

    /**
     * @return array{commission: float, sale_amount: float}|null
     */
    public function executeForCertification(CertificationPurchase $purchase): ?array
    {
        $affiliate = $purchase->affiliate;

        if (! $affiliate) {
            return null;
        }

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

        return $this->record($affiliate, $rate, (float) $certification->price, [
            'certification_purchase_id' => $purchase->id,
            'referred_user_id'          => $purchase->user_id,
        ], 'Certification affiliate commission skipped');
    }

    /**
     * @return array{commission: float, sale_amount: float}|null
     */
    public function executeForCurzzo(\App\Models\CurzzoPurchase $purchase): ?array
    {
        $affiliate = $purchase->affiliate;

        if (! $affiliate) {
            return null;
        }

        $curzzo = $purchase->curzzo;
        $rate   = ($curzzo->affiliate_commission_rate ?? 0) / 100;

        if ($rate <= 0) {
            Log::info('Curzzo affiliate commission skipped — no commission rate set', ['curzzo_id' => $curzzo->id]);
            return null;
        }

        if (AffiliateConversion::where('curzzo_purchase_id', $purchase->id)->exists()) {
            Log::info('Curzzo affiliate commission skipped — already recorded', ['purchase_id' => $purchase->id]);
            return null;
        }

        return $this->record($affiliate, $rate, (float) $curzzo->price, [
            'curzzo_purchase_id' => $purchase->id,
            'referred_user_id'   => $purchase->user_id,
        ], 'Curzzo affiliate commission skipped');
    }

    /**
     * Shared logic for recording a conversion, incrementing totals, and evaluating badges.
     *
     * @return array{commission: float, sale_amount: float}|null
     */
    private function record(Affiliate $affiliate, float $rate, float $saleAmount, array $extraData, string $logPrefix): ?array
    {
        if (! AffiliateSubscriptionChecker::isActivelySubscribed($affiliate->user_id, $affiliate->community_id)) {
            Log::info("{$logPrefix} — affiliate not subscribed", ['affiliate_id' => $affiliate->id]);
            return null;
        }

        $platformFee   = round($saleAmount * $affiliate->community->platformFeeRate(), 2);
        $commission    = round($saleAmount * $rate, 2);
        $creatorAmount = round($saleAmount - $platformFee - $commission, 2);

        AffiliateConversion::create(array_merge($extraData, [
            'affiliate_id'      => $affiliate->id,
            'sale_amount'       => $saleAmount,
            'platform_fee'      => $platformFee,
            'commission_amount' => $commission,
            'creator_amount'    => $creatorAmount,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]));

        $affiliate->increment('total_earned', $commission);

        $affiliateUser = User::find($affiliate->user_id);
        if ($affiliateUser) {
            $this->badges->evaluate($affiliateUser);
        }

        return ['commission' => $commission, 'sale_amount' => $saleAmount];
    }
}
