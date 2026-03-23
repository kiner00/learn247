<?php

namespace App\Services\Payout;

use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\Payment;

/**
 * Single source of truth for computing how much a community owner has earned,
 * paid out, and still has pending. Used by Admin, Creator dashboard, and
 * Community analytics — both Web and API controllers.
 */
class OwnerEarningsCalculator
{
    /**
     * Returns:
     *   gross               – total payments received
     *   platform_fee_rate   – the community's configured platform fee rate
     *   platform_fee        – gross × rate
     *   affiliate_commission– sum of commissions paid/owed to affiliates
     *   earned              – gross − platform_fee − affiliate_commission
     *   paid                – already disbursed via OwnerPayout
     *   pending             – earned − paid (floored at 0)
     */
    public function forCommunity(Community $community): array
    {
        $payments = Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->selectRaw('SUM(amount) as gross, SUM(processing_fee) as processing_fee, SUM(platform_fee) as platform_fee')
            ->first();

        $gross         = (float) $payments->gross;
        $processingFee = (float) $payments->processing_fee;
        $platformFee   = (float) $payments->platform_fee;

        $affiliateCommission = (float) AffiliateConversion::whereHas(
            'affiliate', fn ($q) => $q->where('community_id', $community->id)
        )->sum('commission_amount');

        $earned = round($gross - $processingFee - $platformFee - $affiliateCommission, 2);

        $paid    = (float) OwnerPayout::where('community_id', $community->id)
            ->where('status', '!=', 'failed')
            ->sum('amount');

        $pending = round($earned - $paid, 2);

        return [
            'gross'                => $gross,
            'processing_fee'       => $processingFee,
            'platform_fee'         => $platformFee,
            'platform_fee_rate'    => $community->platformFeeRate(),
            'affiliate_commission' => $affiliateCommission,
            'earned'               => $earned,
            'paid'                 => $paid,
            'pending'              => max(0, $pending),
        ];
    }
}
