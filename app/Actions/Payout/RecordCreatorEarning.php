<?php

namespace App\Actions\Payout;

use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;

class RecordCreatorEarning
{
    public function __construct(private WalletService $wallet) {}

    /**
     * Credit the community owner's wallet for a paid Payment.
     * Net = gross - platform_fee - affiliate_commission (matches CalculateEligibility::forOwner).
     * Idempotent on the Payment as the source.
     */
    public function execute(Payment $payment): ?WalletTransaction
    {
        if ($payment->status !== Payment::STATUS_PAID) {
            return null;
        }

        $community = $payment->community ?? Community::find($payment->community_id);
        $owner = $community?->owner;
        if (! $owner) {
            return null;
        }

        $affiliateCommission = (float) AffiliateConversion::where('payment_id', $payment->id)
            ->sum('commission_amount');

        $net = round((float) $payment->amount - (float) $payment->platform_fee - $affiliateCommission, 2);
        if ($net <= 0) {
            return null;
        }

        return $this->wallet->credit(
            user: $owner,
            source: $payment,
            amount: $net,
            status: WalletTransaction::STATUS_PAID,
            availableAt: now()->addDays(config('affiliate.hold_days', 7)),
            opts: [
                'description' => "Creator earnings — {$community->name}",
                'metadata' => ['payment_id' => $payment->id],
            ],
        );
    }
}
