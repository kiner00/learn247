<?php

use App\Models\AffiliateConversion;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillOwnerCredits();
        $this->backfillOwnerDebits();
        $this->backfillPendingPayoutRequestDebits();
    }

    public function down(): void
    {
        $sources = [
            (new Payment)->getMorphClass(),
            (new OwnerPayout)->getMorphClass(),
            (new PayoutRequest)->getMorphClass(),
        ];
        DB::table('wallet_transactions')->whereIn('source_type', $sources)->delete();

        $this->call('wallet:reconcile --fix');
    }

    private function backfillOwnerCredits(): void
    {
        Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->with('community:id,owner_id,name')
            ->orderBy('id')
            ->chunkById(500, function ($payments) {
                foreach ($payments as $payment) {
                    $owner = $payment->community?->owner;
                    if (! $owner) {
                        continue;
                    }

                    $affCommission = (float) AffiliateConversion::where('payment_id', $payment->id)
                        ->sum('commission_amount');
                    $net = round((float) $payment->amount - (float) $payment->platform_fee - $affCommission, 2);
                    if ($net <= 0) {
                        continue;
                    }

                    $wallet = Wallet::firstOrCreate(
                        ['user_id' => $owner->id, 'currency' => 'PHP'],
                        ['balance' => 0, 'pending_balance' => 0],
                    );

                    $morph = $payment->getMorphClass();

                    $tx = WalletTransaction::firstOrCreate(
                        [
                            'source_type' => $morph,
                            'source_id' => $payment->id,
                            'type' => WalletTransaction::TYPE_CREDIT,
                        ],
                        [
                            'wallet_id' => $wallet->id,
                            'user_id' => $owner->id,
                            'status' => WalletTransaction::STATUS_SETTLED,
                            'amount' => $net,
                            'currency' => 'PHP',
                            'description' => 'Backfilled creator earnings',
                            'metadata' => ['backfill' => true],
                            'available_at' => $payment->paid_at ?? $payment->created_at,
                            'settled_at' => $payment->paid_at ?? $payment->created_at,
                        ],
                    );

                    if ($tx->wasRecentlyCreated) {
                        $wallet->increment('balance', $net);
                    }
                }
            });
    }

    private function backfillOwnerDebits(): void
    {
        OwnerPayout::query()
            ->where('status', '!=', 'failed')
            ->orderBy('id')
            ->chunkById(500, function ($payouts) {
                foreach ($payouts as $payout) {
                    $wallet = Wallet::where('user_id', $payout->user_id)->first();
                    if (! $wallet) {
                        continue;
                    }

                    $morph = $payout->getMorphClass();

                    $tx = WalletTransaction::firstOrCreate(
                        [
                            'source_type' => $morph,
                            'source_id' => $payout->id,
                            'type' => WalletTransaction::TYPE_DEBIT,
                        ],
                        [
                            'wallet_id' => $wallet->id,
                            'user_id' => $payout->user_id,
                            'status' => WalletTransaction::STATUS_WITHDRAWN,
                            'amount' => $payout->amount,
                            'currency' => 'PHP',
                            'description' => 'Backfilled past owner payout',
                            'metadata' => ['backfill' => true],
                            'withdrawn_at' => $payout->paid_at ?? $payout->created_at,
                        ],
                    );

                    if ($tx->wasRecentlyCreated) {
                        $wallet->decrement('balance', $payout->amount);
                    }
                }
            });
    }

    private function backfillPendingPayoutRequestDebits(): void
    {
        PayoutRequest::query()
            ->where('status', PayoutRequest::STATUS_PENDING)
            ->orderBy('id')
            ->chunkById(500, function ($requests) {
                foreach ($requests as $request) {
                    $wallet = Wallet::where('user_id', $request->user_id)->first();
                    if (! $wallet) {
                        continue;
                    }

                    $morph = $request->getMorphClass();

                    $tx = WalletTransaction::firstOrCreate(
                        [
                            'source_type' => $morph,
                            'source_id' => $request->id,
                            'type' => WalletTransaction::TYPE_DEBIT,
                        ],
                        [
                            'wallet_id' => $wallet->id,
                            'user_id' => $request->user_id,
                            'status' => WalletTransaction::STATUS_PENDING,
                            'amount' => $request->amount,
                            'currency' => 'PHP',
                            'description' => 'Backfilled pending payout request',
                            'metadata' => ['backfill' => true],
                        ],
                    );

                    if ($tx->wasRecentlyCreated) {
                        $wallet->decrement('balance', $request->amount);
                    }
                }
            });
    }
};
