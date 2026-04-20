<?php

namespace App\Livewire;

use App\Models\AiUsageLog;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class AiSpendModels extends Card
{
    public function render(): Renderable
    {
        $since = now()->sub($this->periodAsInterval());

        $models = AiUsageLog::query()
            ->selectRaw('model, kind, SUM(total_tokens) as tokens, SUM(cost_usd) as cost, COUNT(*) as calls')
            ->where('created_at', '>=', $since)
            ->whereNotNull('model')
            ->groupBy('model', 'kind')
            ->orderByDesc('cost')
            ->get();

        return View::make('livewire.ai-spend-models', [
            'models' => $models,
        ]);
    }
}
