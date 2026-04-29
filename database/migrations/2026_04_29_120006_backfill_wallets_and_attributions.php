<?php

use App\Models\AffiliateAttribution;
use App\Models\AffiliateConversion;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillWallets();
        $this->backfillAttributions();
    }

    public function down(): void
    {
        DB::table('wallet_transactions')->whereIn('source_type', [
            (new AffiliateConversion)->getMorphClass(),
        ])->delete();

        DB::table('wallets')->delete();
        DB::table('affiliate_attributions')->delete();
    }

    private function backfillWallets(): void
    {
        AffiliateConversion::with('affiliate:id,user_id,community_id')
            ->orderBy('id')
            ->chunkById(500, function ($conversions) {
                foreach ($conversions as $conversion) {
                    $affiliate = $conversion->affiliate;
                    if (! $affiliate) {
                        continue;
                    }

                    $wallet = Wallet::firstOrCreate(
                        ['user_id' => $affiliate->user_id, 'currency' => 'PHP'],
                        ['balance' => 0, 'pending_balance' => 0],
                    );

                    $alreadyWithdrawn = $conversion->status === AffiliateConversion::STATUS_PAID;
                    $morphClass = $conversion->getMorphClass();

                    $credit = WalletTransaction::firstOrCreate(
                        [
                            'source_type' => $morphClass,
                            'source_id' => $conversion->id,
                            'type' => WalletTransaction::TYPE_CREDIT,
                        ],
                        [
                            'wallet_id' => $wallet->id,
                            'user_id' => $affiliate->user_id,
                            'status' => WalletTransaction::STATUS_SETTLED,
                            'amount' => $conversion->commission_amount,
                            'currency' => 'PHP',
                            'description' => 'Backfilled affiliate commission',
                            'metadata' => ['backfill' => true],
                            'available_at' => $conversion->created_at,
                            'settled_at' => $conversion->created_at,
                        ],
                    );

                    if ($credit->wasRecentlyCreated) {
                        $wallet->increment('balance', $conversion->commission_amount);
                    }

                    if ($alreadyWithdrawn) {
                        $debit = WalletTransaction::firstOrCreate(
                            [
                                'source_type' => $morphClass,
                                'source_id' => $conversion->id,
                                'type' => WalletTransaction::TYPE_DEBIT,
                            ],
                            [
                                'wallet_id' => $wallet->id,
                                'user_id' => $affiliate->user_id,
                                'status' => WalletTransaction::STATUS_WITHDRAWN,
                                'amount' => $conversion->commission_amount,
                                'currency' => 'PHP',
                                'description' => 'Backfilled past payout',
                                'metadata' => ['backfill' => true],
                                'withdrawn_at' => $conversion->paid_at ?? $conversion->created_at,
                            ],
                        );

                        if ($debit->wasRecentlyCreated) {
                            $wallet->decrement('balance', $conversion->commission_amount);
                        }
                    }
                }
            });
    }

    private function backfillAttributions(): void
    {
        $rows = DB::table('affiliate_conversions')
            ->join('affiliates', 'affiliates.id', '=', 'affiliate_conversions.affiliate_id')
            ->select(
                'affiliates.community_id',
                'affiliate_conversions.referred_user_id',
                'affiliates.id as affiliate_id',
                DB::raw('MIN(affiliate_conversions.created_at) as first_at'),
            )
            ->whereNotNull('affiliate_conversions.referred_user_id')
            ->groupBy('affiliates.community_id', 'affiliate_conversions.referred_user_id', 'affiliates.id')
            ->orderBy('first_at')
            ->get();

        $seen = [];
        foreach ($rows as $row) {
            $key = $row->community_id.':'.$row->referred_user_id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            AffiliateAttribution::firstOrCreate(
                [
                    'community_id' => $row->community_id,
                    'referred_user_id' => $row->referred_user_id,
                ],
                ['affiliate_id' => $row->affiliate_id],
            );
        }
    }
};
