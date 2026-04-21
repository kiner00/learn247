<?php

namespace App\Console\Commands;

use App\Actions\Account\HardDeleteUserAccount;
use App\Models\User;
use Illuminate\Console\Command;

class PruneDeletedUsers extends Command
{
    protected $signature = 'users:prune-deleted';

    protected $description = 'Hard-delete users whose grace period has expired';

    public function handle(HardDeleteUserAccount $action): int
    {
        $threshold = now()->subDays(User::DELETION_GRACE_DAYS);

        $users = User::onlyTrashed()
            ->where('deleted_at', '<', $threshold)
            ->get();

        foreach ($users as $user) {
            try {
                $action->execute($user);
                $this->line("Pruned user #{$user->id} ({$user->email})");
            } catch (\Throwable $e) {
                $this->error("Failed to prune user #{$user->id}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Pruned {$users->count()} users past the {$threshold->toDateTimeString()} threshold.");

        return self::SUCCESS;
    }
}
