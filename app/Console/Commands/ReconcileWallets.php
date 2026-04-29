<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileWallets extends Command
{
    protected $signature = 'wallet:reconcile {--fix : Persist computed balances to the wallets table}';

    protected $description = 'Recompute wallet balances from the ledger and report drift';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $drift = 0;

        Wallet::query()->orderBy('id')->chunkById(200, function ($wallets) use ($fix, &$drift) {
            foreach ($wallets as $wallet) {
                [$expectedBalance, $expectedPending] = $this->computeBalances($wallet->id);

                $balanceDrift = round((float) $wallet->balance - $expectedBalance, 2);
                $pendingDrift = round((float) $wallet->pending_balance - $expectedPending, 2);

                if ($balanceDrift !== 0.0 || $pendingDrift !== 0.0) {
                    $drift++;
                    $this->warn(sprintf(
                        'Wallet #%d (user_id=%d): balance %.2f→%.2f (drift %.2f), pending %.2f→%.2f (drift %.2f)',
                        $wallet->id, $wallet->user_id,
                        $wallet->balance, $expectedBalance, $balanceDrift,
                        $wallet->pending_balance, $expectedPending, $pendingDrift,
                    ));

                    if ($fix) {
                        $wallet->update([
                            'balance' => $expectedBalance,
                            'pending_balance' => $expectedPending,
                        ]);
                    }
                }
            }
        });

        if ($drift === 0) {
            $this->info('All wallets reconciled cleanly.');
        } else {
            $this->info("Found {$drift} wallet(s) with drift.".($fix ? ' Persisted corrections.' : ' Re-run with --fix to persist.'));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: float, 1: float}  [balance, pending_balance]
     */
    private function computeBalances(int $walletId): array
    {
        $rows = DB::table('wallet_transactions')
            ->selectRaw('type, status, COALESCE(SUM(amount), 0) as total')
            ->where('wallet_id', $walletId)
            ->groupBy('type', 'status')
            ->get();

        $balance = 0.0;
        $pending = 0.0;

        foreach ($rows as $row) {
            $total = (float) $row->total;
            if ($row->type === WalletTransaction::TYPE_CREDIT) {
                if ($row->status === WalletTransaction::STATUS_SETTLED) {
                    $balance += $total;
                } elseif ($row->status === WalletTransaction::STATUS_PAID) {
                    $pending += $total;
                }
            } elseif ($row->type === WalletTransaction::TYPE_DEBIT) {
                if (in_array($row->status, [WalletTransaction::STATUS_PENDING, WalletTransaction::STATUS_WITHDRAWN], true)) {
                    $balance -= $total;
                }
            }
        }

        return [round($balance, 2), round($pending, 2)];
    }
}
