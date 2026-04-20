<?php

namespace App\Livewire;

use App\Models\AiUsageLog;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class AiSpendUsers extends Card
{
    public function render(): Renderable
    {
        $since = now()->sub($this->periodAsInterval());

        $users = AiUsageLog::query()
            ->selectRaw('user_id, SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as calls')
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('cost')
            ->limit(15)
            ->with('user:id,name,email,username')
            ->get();

        $totals = AiUsageLog::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->selectRaw('SUM(cost_usd) as cost, SUM(total_tokens) as tokens, COUNT(*) as calls')
            ->first();

        return View::make('livewire.ai-spend-users', [
            'users' => $users,
            'totals' => $totals,
        ]);
    }
}
