<?php

namespace App\Actions\Affiliate;

use App\Models\AffiliateConversion;
use App\Models\Payment;
use App\Models\Subscription;

class RecordAffiliateConversion
{
    private const PLATFORM_FEE_RATE = 0.03;

    public function execute(Subscription $subscription, Payment $payment): void
    {
        $affiliate = $subscription->affiliate;

        if (! $affiliate) {
            return;
        }

        $rate            = $affiliate->community->affiliate_commission_rate / 100;
        $saleAmount      = (float) $payment->amount;
        $platformFee     = round($saleAmount * self::PLATFORM_FEE_RATE, 2);
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
    }
}
