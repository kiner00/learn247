<?php

namespace App\Console\Commands;

use App\Mail\AiBudgetAlert;
use App\Models\AiUsageLog;
use App\Models\Community;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class CheckAiBudgets extends Command
{
    protected $signature = 'ai:check-budgets';

    protected $description = 'Alert admins when AI spend crosses configured thresholds per user or community.';

    public function handle(): int
    {
        $to = config('ai_budgets.alerts.to');
        $threshold = (float) config('ai_budgets.alerts.threshold_usd', 0);
        $window = (int) config('ai_budgets.alerts.window_minutes', 60);
        $cooldown = (int) config('ai_budgets.alerts.cooldown_minutes', 360);

        if (! $to || $threshold <= 0) {
            $this->info('AI alerts not configured (AI_ALERT_EMAIL or threshold missing) — skipping.');

            return self::SUCCESS;
        }

        $since = now()->subMinutes($window);

        $this->checkScope('community', Community::class, $since, $threshold, $window, $cooldown, $to);
        $this->checkScope('user', User::class, $since, $threshold, $window, $cooldown, $to);

        return self::SUCCESS;
    }

    private function checkScope(
        string $scope,
        string $modelClass,
        \Carbon\CarbonInterface $since,
        float $threshold,
        int $window,
        int $cooldown,
        string $to,
    ): void {
        $column = "{$scope}_id";

        $rows = AiUsageLog::query()
            ->selectRaw("{$column} as scope_id, SUM(cost_usd) as cost")
            ->where('created_at', '>=', $since)
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('SUM(cost_usd) >= ?', [$threshold])
            ->get();

        foreach ($rows as $row) {
            $cacheKey = "ai-budget-alert:{$scope}:{$row->scope_id}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            $entity = $modelClass::find($row->scope_id);
            $label = $entity?->name ?? $entity?->email ?? "#{$row->scope_id}";

            Mail::to($to)->send(new AiBudgetAlert(
                scope: $scope,
                scopeId: (int) $row->scope_id,
                scopeLabel: $label,
                spent: (float) $row->cost,
                threshold: $threshold,
                windowMinutes: $window,
            ));

            Cache::put($cacheKey, true, now()->addMinutes($cooldown));

            $this->info("Alert sent: {$scope} {$label} spent \${$row->cost} in last {$window}m");
        }
    }
}
