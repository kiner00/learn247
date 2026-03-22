<?php

namespace App\Services\Analytics;

use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Builds the 6-month analytics payload for a creator's dashboard.
 * Extracted from CreatorController::buildAnalytics() so the API creator
 * dashboard can consume the same data without duplication.
 */
class CreatorAnalyticsService
{
    public function build(int $userId): array
    {
        $communityIds = Community::where('owner_id', $userId)->pluck('id');

        $months = collect(range(5, 0))->map(fn ($i) => Carbon::now()->subMonths($i));
        $labels = $months->map(fn ($m) => $m->format('M Y'))->values()->toArray();

        // Monthly revenue
        $revenueRaw = Payment::whereIn('community_id', $communityIds)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $revenue = $months->map(fn ($m) => (float) ($revenueRaw[$m->format('Y-m')] ?? 0))->values()->toArray();

        // New members per month
        $newMembersRaw = Subscription::whereIn('community_id', $communityIds)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('created_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $newMembers = $months->map(fn ($m) => (int) ($newMembersRaw[$m->format('Y-m')] ?? 0))->values()->toArray();

        // Churn per month
        $churnRaw = Subscription::whereIn('community_id', $communityIds)
            ->whereIn('status', [Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELLED])
            ->where('updated_at', '>=', Carbon::now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(updated_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $churn = $months->map(fn ($m) => (int) ($churnRaw[$m->format('Y-m')] ?? 0))->values()->toArray();

        // Retention rate (last 30 days)
        $active30  = Subscription::whereIn('community_id', $communityIds)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->count();
        $expired30 = Subscription::whereIn('community_id', $communityIds)
            ->whereIn('status', [Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELLED])
            ->where('updated_at', '>=', Carbon::now()->subDays(30))
            ->count();

        $retentionRate = ($active30 + $expired30) > 0
            ? round(($active30 / ($active30 + $expired30)) * 100, 1)
            : 100.0;

        // MRR
        $mrr = Subscription::whereIn('community_id', $communityIds)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->join('communities', 'subscriptions.community_id', '=', 'communities.id')
            ->sum('communities.price');

        return compact('labels', 'revenue', 'newMembers', 'churn', 'retentionRate', 'mrr');
    }
}
