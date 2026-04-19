<?php

namespace App\Listeners;

use App\Models\AiUsageLog;
use App\Services\Ai\CostCalculator;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\ImageGenerated;
use Laravel\Ai\Responses\AgentResponse;

class LogAiUsage
{
    public function handleAgentPrompted(AgentPrompted $event): void
    {
        // Streamed responses don't have final usage at emit time; skip.
        if (! $event->response instanceof AgentResponse) {
            return;
        }

        $usage = $event->response->usage;
        $meta = $event->response->meta;

        $this->record([
            'kind' => 'agent',
            'provider' => $meta->provider,
            'model' => $meta->model,
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'cost_usd' => CostCalculator::agentCost($meta->model, $usage->promptTokens, $usage->completionTokens),
            'invocation_id' => $event->invocationId,
        ]);
    }

    public function handleImageGenerated(ImageGenerated $event): void
    {
        $imageCount = is_countable($event->response->images ?? null)
            ? count($event->response->images)
            : 1;

        $this->record([
            'kind' => 'image',
            'provider' => method_exists($event->provider, 'name') ? $event->provider->name() : null,
            'model' => $event->model,
            'prompt_tokens' => $event->response->usage->promptTokens ?? 0,
            'completion_tokens' => $event->response->usage->completionTokens ?? 0,
            'cost_usd' => CostCalculator::imageCost($imageCount),
            'invocation_id' => $event->invocationId,
        ]);
    }

    private function record(array $attrs): void
    {
        try {
            AiUsageLog::create([
                ...$attrs,
                'community_id' => Context::get('ai.community_id'),
                'user_id' => Context::get('ai.user_id') ?? auth()->id(),
                'total_tokens' => ($attrs['prompt_tokens'] ?? 0) + ($attrs['completion_tokens'] ?? 0),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let logging break the AI call.
            Log::warning('AI usage logging failed', ['error' => $e->getMessage()]);
        }
    }
}
