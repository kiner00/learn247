<?php

namespace App\Actions\Payout;

use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use App\Support\CacheKeys;

class RequestWalletWithdrawal
{
    public const MIN_AMOUNT = 100;

    public function __construct(private WalletService $wallet) {}

    /**
     * @return array{success: bool, message: string, request_id?: int}
     */
    public function execute(User $user, float $amount): array
    {
        if (! $user->isKycVerified()) {
            return ['success' => false, 'message' => 'KYC verification is required before withdrawing.'];
        }

        if (! in_array($user->payout_method, ['gcash', 'maya']) || ! $user->payout_details) {
            return ['success' => false, 'message' => 'Please set your payout method in Account Settings before withdrawing.'];
        }

        if ($amount < self::MIN_AMOUNT) {
            return ['success' => false, 'message' => 'Minimum withdrawal is ₱'.self::MIN_AMOUNT.'.'];
        }

        $hasOpen = PayoutRequest::where('user_id', $user->id)
            ->where('type', PayoutRequest::TYPE_WALLET)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->exists();

        if ($hasOpen) {
            return ['success' => false, 'message' => 'You already have an open wallet withdrawal. Wait for it to be processed before requesting another.'];
        }

        $balance = (float) $this->wallet->balanceOf($user)['balance'];
        if ($balance < $amount) {
            return ['success' => false, 'message' => "Amount exceeds wallet balance of ₱{$balance}."];
        }

        $request = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_WALLET,
            'amount' => $amount,
            'eligible_amount' => $balance,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $this->wallet->debit(
            user: $user,
            source: $request,
            amount: $amount,
            status: WalletTransaction::STATUS_PENDING,
            opts: [
                'description' => 'Wallet withdrawal request',
                'metadata' => ['payout_request_id' => $request->id],
            ],
        );

        CacheKeys::flushAdmin();

        return [
            'success' => true,
            'message' => 'Withdrawal request submitted. The admin will review and process it shortly.',
            'request_id' => $request->id,
        ];
    }
}
