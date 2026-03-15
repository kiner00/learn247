<?php

namespace App\Queries\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetAffiliateAnalytics
{
    public function execute(User $user, ?string $period = 'month', ?int $communityId = null): array
    {
        $affiliateQuery = Affiliate::where('user_id', $user->id);
        $allAffiliateIds = (clone $affiliateQuery)->pluck('id');

        $filteredIds = $communityId
            ? (clone $affiliateQuery)->where('community_id', $communityId)->pluck('id')
            : $allAffiliateIds;

        $from = $this->periodStart($period);

        $base = AffiliateConversion::whereIn('affiliate_id', $filteredIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        $totalEarned      = (float) (clone $base)->sum('commission_amount');
        $totalPaid        = (float) (clone $base)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount');
        $totalPending     = (float) (clone $base)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount');
        $totalConversions = (clone $base)->count();
        $avgPerReferral   = $totalConversions > 0 ? round($totalEarned / $totalConversions, 2) : 0;

        $bestMonth = AffiliateConversion::whereIn('affiliate_id', $allAffiliateIds)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(commission_amount) as total")
            ->groupBy('month')
            ->orderByDesc('total')
            ->first();

        $chartData = match ($period) {
            'week'  => $this->chartByDay($filteredIds, $from, 7),
            'month' => $this->chartByDay($filteredIds, $from, Carbon::now()->daysInMonth),
            'year'  => $this->chartByMonth($filteredIds, $from),
            default => $this->chartByMonth($filteredIds, Carbon::now()->subYears(2)),
        };

        $conversions = (clone $base)
            ->with(['affiliate.community'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'date'              => $c->created_at->format('M j, Y'),
                'community'         => $c->affiliate->community->name,
                'sale_amount'       => (float) $c->sale_amount,
                'commission_amount' => (float) $c->commission_amount,
                'status'            => $c->status,
                'paid_at'           => $c->paid_at?->format('M j, Y'),
            ]);

        $communities = Affiliate::where('user_id', $user->id)
            ->with('community:id,name,slug')
            ->get()
            ->map(fn ($a) => ['id' => $a->community_id, 'name' => $a->community->name]);

        $byComm = AffiliateConversion::whereIn('affiliate_id', $allAffiliateIds)
            ->select('affiliate_id', DB::raw('SUM(commission_amount) as total'))
            ->with('affiliate.community:id,name')
            ->groupBy('affiliate_id')
            ->get()
            ->map(fn ($c) => ['community' => $c->affiliate->community->name, 'total' => (float) $c->total])
            ->sortByDesc('total')
            ->values();

        return [
            'summary' => [
                'total_earned'      => $totalEarned,
                'total_paid'        => $totalPaid,
                'total_pending'     => $totalPending,
                'total_conversions' => $totalConversions,
                'avg_per_referral'  => $avgPerReferral,
                'best_month'        => $bestMonth?->month,
                'best_month_total'  => (float) ($bestMonth?->total ?? 0),
            ],
            'chartData'   => $chartData,
            'conversions' => $conversions,
            'communities' => $communities,
            'byComm'      => $byComm,
        ];
    }

    public function chartByDay(Collection $affiliateIds, Carbon $from, int $days): array
    {
        $rows = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->where('created_at', '>=', $from)
            ->selectRaw("DATE(created_at) as label, SUM(commission_amount) as total")
            ->groupBy('label')
            ->pluck('total', 'label');

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $result[] = ['label' => Carbon::parse($d)->format('M j'), 'total' => (float) ($rows[$d] ?? 0)];
        }
        return $result;
    }

    public function chartByMonth(Collection $affiliateIds, Carbon $from): array
    {
        $rows = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as label, SUM(commission_amount) as total")
            ->groupBy('label')
            ->pluck('total', 'label');

        $result = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= Carbon::now()) {
            $key = $cursor->format('Y-m');
            $result[] = ['label' => $cursor->format('M Y'), 'total' => (float) ($rows[$key] ?? 0)];
            $cursor->addMonth();
        }
        return $result;
    }

    private function periodStart(?string $period): ?Carbon
    {
        return match ($period) {
            'week'  => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year'  => Carbon::now()->startOfYear(),
            default => null,
        };
    }
}
