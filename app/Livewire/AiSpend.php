<?php

namespace App\Livewire;

use App\Models\AiUsageLog;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class AiSpend extends Card
{
    public function render(): Renderable
    {
        $since = now()->sub($this->periodAsInterval());

        $communities = AiUsageLog::query()
            ->selectRaw('community_id, SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as calls')
            ->where('created_at', '>=', $since)
            ->whereNotNull('community_id')
            ->groupBy('community_id')
            ->orderByDesc('cost')
            ->limit(15)
            ->with('community:id,name,slug')
            ->get();

        $totals = AiUsageLog::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('SUM(cost_usd) as cost, SUM(total_tokens) as tokens, COUNT(*) as calls')
            ->first();

        return View::make('livewire.ai-spend', [
            'communities' => $communities,
            'totals' => $totals,
        ]);
    }
}
