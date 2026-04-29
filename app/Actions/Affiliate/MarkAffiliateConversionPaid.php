<?php

namespace App\Actions\Affiliate;

use App\Models\AffiliateConversion;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use App\Support\CacheKeys;

class MarkAffiliateConversionPaid
{
    public function __construct(private WalletService $wallet) {}

    public function execute(AffiliateConversion $conversion): void
    {
        $conversion->update([
            'status' => AffiliateConversion::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $conversion->affiliate->increment('total_paid', (float) $conversion->commission_amount);

        $this->settleAndWithdraw($conversion);

        CacheKeys::flushCommunity($conversion->affiliate->community_id);
        CacheKeys::flushAdmin();
    }

    private function settleAndWithdraw(AffiliateConversion $conversion): void
    {
        $credit = $conversion->walletTransactions()
            ->where('type', WalletTransaction::TYPE_CREDIT)
            ->first();

        if (! $credit) {
            return;
        }

        $affiliateUser = User::find($conversion->affiliate->user_id);
        if (! $affiliateUser) {
            return;
        }

        if ($credit->status === WalletTransaction::STATUS_PAID) {
            $this->wallet->transition($credit, WalletTransaction::STATUS_SETTLED);
        }

        $this->wallet->debit(
            user: $affiliateUser,
            source: $conversion,
            amount: (float) $conversion->commission_amount,
            opts: [
                'description' => 'Affiliate payout (admin marked paid)',
                'metadata' => ['legacy_mark_paid' => true],
            ],
        );
    }
}
