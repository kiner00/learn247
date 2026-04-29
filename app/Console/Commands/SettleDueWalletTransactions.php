<?php

namespace App\Console\Commands;

use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;

class SettleDueWalletTransactions extends Command
{
    protected $signature = 'wallet:settle-due';

    protected $description = 'Promote paid wallet credits whose hold period has elapsed to settled';

    public function handle(WalletService $wallet): int
    {
        $promoted = $wallet->settleDue();

        $this->info("Settled {$promoted} wallet transaction(s).");

        return self::SUCCESS;
    }
}
