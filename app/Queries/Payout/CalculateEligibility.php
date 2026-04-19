<?php

namespace App\Queries\Payout;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PayoutRequest;
use Carbon\Carbon;

class CalculateEligibility
{
    const HOLD_DAYS = 7;

    /**
     * @return array{0: float, 1: float, 2: string|null} [eligibleNow, lockedAmount, nextEligibleDate]
     */
    public function forOwner(Community $community): array
    {
        $cutoff = now()->subDays(self::HOLD_DAYS);

        $eligiblePayments = Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '<=', $cutoff)
            ->selectRaw('SUM(amount) as gross')
            ->first();

        $eligibleGross = (float) $eligiblePayments->gross;
        $eligiblePlatformFee = round($eligibleGross * $community->platformFeeRate(), 2);

        $lockedPayments = Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>', $cutoff)
            ->selectRaw('SUM(amount) as gross')
            ->first();

        $lockedGross = (float) $lockedPayments->gross;
        $lockedPlatformFee = round($lockedGross * $community->platformFeeRate(), 2);

        $affiliateCommission = (float) AffiliateConversion::whereHas(
            'affiliate', fn ($q) => $q->where('community_id', $community->id)
        )->sum('commission_amount');

        // Prorate affiliate commissions between eligible and locked buckets
        // proportionally to their gross, so neither bucket is unfairly depleted.
        $totalGross = $eligibleGross + $lockedGross;
        if ($totalGross > 0) {
            $eligibleCommission = round($affiliateCommission * ($eligibleGross / $totalGross), 2);
            $lockedCommission = round($affiliateCommission * ($lockedGross / $totalGross), 2);
        } else {
            $eligibleCommission = 0.0;
            $lockedCommission = 0.0;
        }

        $eligibleEarned = round($eligibleGross - $eligiblePlatformFee - $eligibleCommission, 2);
        $lockedEarned = round($lockedGross - $lockedPlatformFee - $lockedCommission, 2);

        $alreadyPaid = (float) OwnerPayout::where('community_id', $community->id)
            ->where('status', '!=', 'failed')
            ->sum('amount');

        $pendingRequested = (float) PayoutRequest::where('community_id', $community->id)
            ->where('type', PayoutRequest::TYPE_OWNER)
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->sum('amount');

        $eligibleNow = max(0, round($eligibleEarned - $alreadyPaid - $pendingRequested, 2));

        $oldestLocked = Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>', $cutoff)
            ->orderBy('paid_at')
            ->value('paid_at');

        $nextEligibleDate = $oldestLocked
            ? Carbon::parse($oldestLocked)->addDays(self::HOLD_DAYS)->toDateString()
            : null;

        return [$eligibleNow, max(0, $lockedEarned), $nextEligibleDate];
    }

    public function forAffiliate(Affiliate $affiliate): float
    {
        $eligible = (float) AffiliateConversion::where('affiliate_id', $affiliate->id)
            ->where('status', AffiliateConversion::STATUS_PENDING)
            ->where('created_at', '<=', now()->subDays(self::HOLD_DAYS))
            ->sum('commission_amount');

        $inFlight = (float) PayoutRequest::where('affiliate_id', $affiliate->id)
            ->where('type', PayoutRequest::TYPE_AFFILIATE)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->sum('amount');

        return max(0, round($eligible - $inFlight, 2));
    }
}
