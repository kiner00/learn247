<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Console\Command;

class EvaluateBadgesForAllUsers extends Command
{
    protected $signature   = 'badges:evaluate-all';
    protected $description = 'Evaluate and award badges for all users (backfill)';

    public function handle(BadgeService $badgeService): void
    {
        $total = User::count();
        $bar   = $this->output->createProgressBar($total);
        $bar->start();

        User::orderBy('id')->chunk(100, function ($users) use ($badgeService, $bar) {
            foreach ($users as $user) {
                $badgeService->evaluate($user);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done — evaluated badges for {$total} users.");
    }
}
