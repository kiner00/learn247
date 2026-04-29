<?php

namespace App\Actions\CreatorPlanAffiliate;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\CreatorSubscription;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Log;

class RecordCreatorPlanAffiliateConversion
{
    public function __construct(private WalletService $wallet) {}

    /**
     * Records a single creator-plan affiliate conversion for one paid cycle.
     *
     * Annual: only one conversion per CreatorSubscription (covers the full year).
     * Monthly: up to {creator_plan_affiliate_max_months} conversions per CreatorSubscription.
     *          A cancelled/expired subscription ends the cohort — a new subscription starts fresh.
     *
     * @return array{commission: float, sale_amount: float}|null
     */
    public function execute(CreatorSubscription $creatorSub, Payment $payment): ?array
    {
        if (! $creatorSub->affiliate_id) {
            return null;
        }

        $affiliate = $creatorSub->affiliate()->first();
        if (! $affiliate || ! $affiliate->isActive() || ! $affiliate->isCreatorPlan()) {
            return null;
        }

        if (AffiliateConversion::where('payment_id', $payment->id)
            ->where('creator_subscription_id', $creatorSub->id)
            ->exists()) {
            return null;
        }

        $existingCount = AffiliateConversion::where('creator_subscription_id', $creatorSub->id)->count();
        $maxMonths = (int) Setting::get('creator_plan_affiliate_max_months', 12);

        if ($creatorSub->isAnnual() && $existingCount >= 1) {
            Log::info('Creator plan affiliate skipped — annual already paid out', [
                'creator_subscription_id' => $creatorSub->id,
            ]);

            return null;
        }

        if (! $creatorSub->isAnnual() && $existingCount >= $maxMonths) {
            Log::info('Creator plan affiliate skipped — monthly cap reached', [
                'creator_subscription_id' => $creatorSub->id,
                'max_months' => $maxMonths,
            ]);

            return null;
        }

        $rate = (float) Setting::get('creator_plan_affiliate_commission_rate', 20) / 100;
        $saleAmount = (float) $payment->amount;
        $commission = round($saleAmount * $rate, 2);

        if ($commission <= 0) {
            return null;
        }

        $platformFee = round($saleAmount - $commission, 2);
        $cohortStart = $existingCount === 0
            ? ($payment->paid_at ?? now())
            : AffiliateConversion::where('creator_subscription_id', $creatorSub->id)
                ->orderBy('id')
                ->value('cohort_start_at');

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'creator_subscription_id' => $creatorSub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $creatorSub->user_id,
            'sale_amount' => $saleAmount,
            'platform_fee' => $platformFee,
            'commission_amount' => $commission,
            'creator_amount' => 0,
            'status' => AffiliateConversion::STATUS_PENDING,
            'is_lifetime' => false,
            'billing_month_index' => $existingCount + 1,
            'cohort_start_at' => $cohortStart,
        ]);

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
                    'description' => 'Creator Plan affiliate commission',
                    'metadata' => [
                        'scope' => Affiliate::SCOPE_CREATOR_PLAN,
                        'billing_month_index' => $existingCount + 1,
                    ],
                ],
            );
        }

        return ['commission' => $commission, 'sale_amount' => $saleAmount];
    }
}
