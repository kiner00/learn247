<?php

namespace App\Actions\Admin;

use App\Models\PayoutRequest;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use App\Support\CacheKeys;

class RejectPayoutRequest
{
    public function __construct(private WalletService $wallet) {}

    public function execute(PayoutRequest $payoutRequest, ?string $reason): void
    {
        abort_unless($payoutRequest->isPending(), 422, 'Request is no longer pending.');

        $payoutRequest->update([
            'status' => PayoutRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'processed_at' => now(),
            'processed_by' => auth()->id(),
        ]);

        $debit = WalletTransaction::where('source_type', $payoutRequest->getMorphClass())
            ->where('source_id', $payoutRequest->id)
            ->where('type', WalletTransaction::TYPE_DEBIT)
            ->where('status', WalletTransaction::STATUS_PENDING)
            ->first();

        if ($debit) {
            $this->wallet->transition($debit, WalletTransaction::STATUS_REVERSED);
        }

        CacheKeys::flushAdmin();
        CacheKeys::flushCreator($payoutRequest->user_id);
    }
}
