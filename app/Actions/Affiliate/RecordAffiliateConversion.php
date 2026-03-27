<?php

namespace App\Actions\Affiliate;

use App\Models\AffiliateConversion;
use App\Models\CertificationPurchase;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Support\Facades\Log;

class RecordAffiliateConversion
{
    public function execute(Subscription $subscription, Payment $payment): void
    {
        $affiliate = $subscription->affiliate;

        if (! $affiliate) {
            return;
        }

        // Affiliate must be subscribed to earn commission (null expires_at = lifetime/one-time billing)
        $affiliateSubscribed = Subscription::where('user_id', $affiliate->user_id)
            ->where('community_id', $affiliate->community_id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        if (! $affiliateSubscribed) {
            Log::info('Affiliate commission skipped — affiliate not subscribed', ['affiliate_id' => $affiliate->id]);
            return;
        }

        $rate            = $affiliate->community->affiliate_commission_rate / 100;
        $saleAmount      = (float) $payment->amount;
        $platformFee     = round($saleAmount * $affiliate->community->platformFeeRate(), 2);
        $commission      = round($saleAmount * $rate, 2);
        $creatorAmount   = round($saleAmount - $platformFee - $commission, 2);

        AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $subscription->id,
            'payment_id'        => $payment->id,
            'referred_user_id'  => $subscription->user_id,
            'sale_amount'       => $saleAmount,
            'platform_fee'      => $platformFee,
            'commission_amount' => $commission,
            'creator_amount'    => $creatorAmount,
            'status'            => AffiliateConversion::STATUS_PENDING,
        ]);

        $affiliate->increment('total_earned', $commission);

        // Check affiliate-related badges for the affiliate user
        $affiliateUser = User::find($affiliate->user_id);
        if ($affiliateUser) {
            app(BadgeService::class)->evaluate($affiliateUser);
        }
    }

    /**
     * Record commission for a paid course enrollment.
     * Uses the course's own affiliate_commission_rate (set by the creator).
     *
     * @return array{commission: float, sale_amount: float}|null  null if skipped
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

        // Affiliate must be subscribed to earn commission (null expires_at = lifetime/one-time billing)
        $affiliateSubscribed = \App\Models\Subscription::where('user_id', $affiliate->user_id)
            ->where('community_id', $affiliate->community_id)
            ->where('status', \App\Models\Subscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        if (! $affiliateSubscribed) {
            Log::info('Course affiliate commission skipped — affiliate not subscribed', ['affiliate_id' => $affiliate->id]);
            return null;
        }

        $saleAmount    = (float) $course->price;
        $platformFee   = round($saleAmount * $affiliate->community->platformFeeRate(), 2);
        $commission    = round($saleAmount * $rate, 2);
        $creatorAmount = round($saleAmount - $platformFee - $commission, 2);

        AffiliateConversion::create([
            'affiliate_id'          => $affiliate->id,
            'course_enrollment_id'  => $enrollment->id,
            'referred_user_id'      => $enrollment->user_id,
            'sale_amount'           => $saleAmount,
            'platform_fee'          => $platformFee,
            'commission_amount'     => $commission,
            'creator_amount'        => $creatorAmount,
            'status'                => AffiliateConversion::STATUS_PENDING,
        ]);

        $affiliate->increment('total_earned', $commission);

        $affiliateUser = User::find($affiliate->user_id);
        if ($affiliateUser) {
            app(BadgeService::class)->evaluate($affiliateUser);
        }

        return ['commission' => $commission, 'sale_amount' => $saleAmount];
    }

    /**
     * Record commission for a paid certification purchase.
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

        // Affiliate must be subscribed to earn commission
        $affiliateSubscribed = Subscription::where('user_id', $affiliate->user_id)
            ->where('community_id', $affiliate->community_id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        if (! $affiliateSubscribed) {
            Log::info('Certification affiliate commission skipped — affiliate not subscribed', ['affiliate_id' => $affiliate->id]);
            return null;
        }

        // Prevent duplicate conversions
        if (AffiliateConversion::where('certification_purchase_id', $purchase->id)->exists()) {
            Log::info('Certification affiliate commission skipped — already recorded', ['purchase_id' => $purchase->id]);
            return null;
        }

        $saleAmount    = (float) $certification->price;
        $platformFee   = round($saleAmount * $affiliate->community->platformFeeRate(), 2);
        $commission    = round($saleAmount * $rate, 2);
        $creatorAmount = round($saleAmount - $platformFee - $commission, 2);

        AffiliateConversion::create([
            'affiliate_id'               => $affiliate->id,
            'certification_purchase_id'  => $purchase->id,
            'referred_user_id'           => $purchase->user_id,
            'sale_amount'                => $saleAmount,
            'platform_fee'               => $platformFee,
            'commission_amount'          => $commission,
            'creator_amount'             => $creatorAmount,
            'status'                     => AffiliateConversion::STATUS_PENDING,
        ]);

        $affiliate->increment('total_earned', $commission);

        $affiliateUser = User::find($affiliate->user_id);
        if ($affiliateUser) {
            app(BadgeService::class)->evaluate($affiliateUser);
        }

        return ['commission' => $commission, 'sale_amount' => $saleAmount];
    }
}
