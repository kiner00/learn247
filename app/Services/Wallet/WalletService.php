<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class WalletService
{
    public function walletFor(User $user, string $currency = 'PHP'): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => $currency],
            ['balance' => 0, 'pending_balance' => 0],
        );
    }

    public function balanceOf(User $user, string $currency = 'PHP'): array
    {
        $wallet = $this->walletFor($user, $currency);

        return [
            'balance' => (float) $wallet->balance,
            'pending_balance' => (float) $wallet->pending_balance,
        ];
    }

    public function credit(
        User $user,
        Model $source,
        float $amount,
        string $status = WalletTransaction::STATUS_PAID,
        ?Carbon $availableAt = null,
        array $opts = [],
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($user, $source, $amount, $status, $availableAt, $opts) {
            $wallet = $this->walletFor($user, $opts['currency'] ?? 'PHP');
            $wallet = Wallet::lockForUpdate()->find($wallet->id);

            $existing = WalletTransaction::where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->where('type', WalletTransaction::TYPE_CREDIT)
                ->first();

            if ($existing) {
                return $existing;
            }

            $tx = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => WalletTransaction::TYPE_CREDIT,
                'status' => $status,
                'amount' => $amount,
                'currency' => $wallet->currency,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'description' => $opts['description'] ?? null,
                'metadata' => $opts['metadata'] ?? null,
                'available_at' => $availableAt,
                'settled_at' => $status === WalletTransaction::STATUS_SETTLED ? now() : null,
            ]);

            $this->applyBalance($wallet, $tx, fromStatus: null, toStatus: $status);

            return $tx;
        });
    }

    public function debit(
        User $user,
        Model $source,
        float $amount,
        string $status = WalletTransaction::STATUS_WITHDRAWN,
        array $opts = [],
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit amount must be positive.');
        }

        if (! in_array($status, [WalletTransaction::STATUS_PENDING, WalletTransaction::STATUS_WITHDRAWN], true)) {
            throw new InvalidArgumentException("Invalid initial debit status: {$status}");
        }

        return DB::transaction(function () use ($user, $source, $amount, $status, $opts) {
            $wallet = $this->walletFor($user, $opts['currency'] ?? 'PHP');
            $wallet = Wallet::lockForUpdate()->find($wallet->id);

            $existing = WalletTransaction::where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->where('type', WalletTransaction::TYPE_DEBIT)
                ->first();

            if ($existing) {
                return $existing;
            }

            if ((float) $wallet->balance < $amount) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $tx = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => WalletTransaction::TYPE_DEBIT,
                'status' => $status,
                'amount' => $amount,
                'currency' => $wallet->currency,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'description' => $opts['description'] ?? null,
                'metadata' => $opts['metadata'] ?? null,
                'withdrawn_at' => $status === WalletTransaction::STATUS_WITHDRAWN ? now() : null,
            ]);

            $wallet->decrement('balance', $amount);

            return $tx;
        });
    }

    public function transition(WalletTransaction $tx, string $newStatus): WalletTransaction
    {
        return DB::transaction(function () use ($tx, $newStatus) {
            $tx = WalletTransaction::lockForUpdate()->find($tx->id);
            $wallet = Wallet::lockForUpdate()->find($tx->wallet_id);

            $this->assertLegalTransition($tx, $newStatus);

            $oldStatus = $tx->status;
            $tx->status = $newStatus;
            $tx->{$this->timestampFor($newStatus)} = now();
            $tx->save();

            $this->applyBalance($wallet, $tx, fromStatus: $oldStatus, toStatus: $newStatus);

            return $tx->refresh();
        });
    }

    public function settleDue(?Carbon $now = null): int
    {
        $now ??= now();

        $count = 0;
        WalletTransaction::query()
            ->where('type', WalletTransaction::TYPE_CREDIT)
            ->where('status', WalletTransaction::STATUS_PAID)
            ->whereNotNull('available_at')
            ->where('available_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(200, function ($txs) use (&$count) {
                foreach ($txs as $tx) {
                    $this->transition($tx, WalletTransaction::STATUS_SETTLED);
                    $count++;
                }
            });

        return $count;
    }

    private function applyBalance(Wallet $wallet, WalletTransaction $tx, ?string $fromStatus, string $toStatus): void
    {
        $amount = (float) $tx->amount;
        $isCredit = $tx->type === WalletTransaction::TYPE_CREDIT;

        $impact = fn (string $status): array => match (true) {
            $isCredit && $status === WalletTransaction::STATUS_PAID => ['pending' => $amount, 'balance' => 0.0],
            $isCredit && $status === WalletTransaction::STATUS_SETTLED => ['pending' => 0.0, 'balance' => $amount],
            $isCredit && $status === WalletTransaction::STATUS_REVERSED && $fromStatus === WalletTransaction::STATUS_SETTLED => ['pending' => 0.0, 'balance' => -$amount],
            $isCredit && $status === WalletTransaction::STATUS_REVERSED && $fromStatus === WalletTransaction::STATUS_PAID => ['pending' => -$amount, 'balance' => 0.0],
            ! $isCredit && $status === WalletTransaction::STATUS_FAILED => ['pending' => 0.0, 'balance' => $amount],
            ! $isCredit && $status === WalletTransaction::STATUS_REVERSED => ['pending' => 0.0, 'balance' => $amount],
            default => ['pending' => 0.0, 'balance' => 0.0],
        };

        $delta = $impact($toStatus);
        $undo = $fromStatus !== null ? $impact($fromStatus) : ['pending' => 0.0, 'balance' => 0.0];

        $pendingDelta = $delta['pending'] - $undo['pending'];
        $balanceDelta = $delta['balance'] - $undo['balance'];

        if ($pendingDelta !== 0.0) {
            $wallet->pending_balance = (float) $wallet->pending_balance + $pendingDelta;
        }
        if ($balanceDelta !== 0.0) {
            $wallet->balance = (float) $wallet->balance + $balanceDelta;
        }
        if ($pendingDelta !== 0.0 || $balanceDelta !== 0.0) {
            $wallet->save();
        }
    }

    private function assertLegalTransition(WalletTransaction $tx, string $newStatus): void
    {
        $legal = [
            WalletTransaction::STATUS_PENDING => [
                WalletTransaction::STATUS_PAID,
                WalletTransaction::STATUS_SETTLED,
                WalletTransaction::STATUS_FAILED,
                WalletTransaction::STATUS_WITHDRAWN,
            ],
            WalletTransaction::STATUS_PAID => [
                WalletTransaction::STATUS_SETTLED,
                WalletTransaction::STATUS_REVERSED,
                WalletTransaction::STATUS_FAILED,
            ],
            WalletTransaction::STATUS_SETTLED => [
                WalletTransaction::STATUS_REVERSED,
            ],
        ];

        $allowed = $legal[$tx->status] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException("Illegal transition {$tx->status} → {$newStatus}.");
        }
    }

    private function timestampFor(string $status): string
    {
        return match ($status) {
            WalletTransaction::STATUS_SETTLED => 'settled_at',
            WalletTransaction::STATUS_WITHDRAWN => 'withdrawn_at',
            WalletTransaction::STATUS_FAILED => 'failed_at',
            WalletTransaction::STATUS_REVERSED => 'reversed_at',
            default => 'updated_at',
        };
    }
}
