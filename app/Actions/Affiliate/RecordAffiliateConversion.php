<?php

namespace App\Actions\Affiliate;

use App\Models\AffiliateConversion;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Support\Facades\Log;

class RecordAffiliateConversion
{
    private const PLATFORM_FEE_RATE = 0.15;

    public function execute(Subscription $subscription, Payment $payment): void
    {
        $affiliate = $subscription->affiliate;

        if (! $affiliate) {
            return;
        }

        // Affiliate must be subscribed to earn commission this month
        $affiliateSubscribed = Subscription::where('user_id', $affiliate->user_id)
            ->where('community_id', $affiliate->community_id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->exists();

        if (! $affiliateSubscribed) {
            Log::info('Affiliate commission skipped — affiliate not subscribed', ['affiliate_id' => $affiliate->id]);
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

        // Check affiliate-related badges for the affiliate user
        $affiliateUser = User::find($affiliate->user_id);
        if ($affiliateUser) {
            app(BadgeService::class)->evaluate($affiliateUser);
        }
    }
}
