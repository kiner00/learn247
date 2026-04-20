<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiUsageController extends Controller
{
    public function index(Request $request): Response
    {
        $days = (int) $request->integer('days', 7);
        $days = max(1, min($days, 90));
        $since = now()->subDays($days);

        $query = AiUsageLog::query()
            ->with(['community:id,name,slug', 'user:id,name,email'])
            ->where('created_at', '>=', $since)
            ->latest('id');

        if ($communityId = $request->integer('community_id')) {
            $query->where('community_id', $communityId);
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($model = $request->string('model')->trim()->toString()) {
            $query->where('model', $model);
        }
        if ($kind = $request->string('kind')->trim()->toString()) {
            $query->where('kind', $kind);
        }

        $logs = $query->paginate(50)->withQueryString();

        $totals = (clone $query)->toBase()->selectRaw('
            COUNT(*) as calls,
            COALESCE(SUM(total_tokens), 0) as tokens,
            COALESCE(SUM(cost_usd), 0) as cost
        ')->first();

        return Inertia::render('Admin/AiUsage', [
            'logs' => $logs->through(fn ($log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toIso8601String(),
                'kind' => $log->kind,
                'provider' => $log->provider,
                'model' => $log->model,
                'prompt_tokens' => $log->prompt_tokens,
                'completion_tokens' => $log->completion_tokens,
                'total_tokens' => $log->total_tokens,
                'cost_usd' => (float) $log->cost_usd,
                'community' => $log->community ? [
                    'id' => $log->community->id,
                    'name' => $log->community->name,
                    'slug' => $log->community->slug,
                ] : null,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
            ]),
            'filters' => [
                'days' => $days,
                'community_id' => $communityId ?: null,
                'user_id' => $userId ?: null,
                'model' => $model ?: null,
                'kind' => $kind ?: null,
            ],
            'totals' => [
                'calls' => (int) ($totals->calls ?? 0),
                'tokens' => (int) ($totals->tokens ?? 0),
                'cost' => (float) ($totals->cost ?? 0),
            ],
        ]);
    }
}
