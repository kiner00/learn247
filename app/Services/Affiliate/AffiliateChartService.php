<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateConversion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Affiliate chart and analytics helpers extracted from AffiliateController.
 * Both index() and analytics() methods share this logic; the API controller
 * will use it too once those endpoints exist.
 */
class AffiliateChartService
{
    public function periodStart(?string $period): ?Carbon
    {
        return match ($period) {
            'week'  => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year'  => Carbon::now()->startOfYear(),
            default => null,
        };
    }

    public function buildChart(mixed $affiliateIds, string $period, ?Carbon $from): array
    {
        if (! $from) {
            $from = Carbon::now()->subYears(2);
        }

        if (in_array($period, ['week', 'month'])) {
            $days = $period === 'week' ? 7 : Carbon::now()->daysInMonth;
            $rows = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
                ->where('created_at', '>=', $from)
                ->selectRaw("DATE(created_at) as label, SUM(commission_amount) as total")
                ->groupBy('label')
                ->pluck('total', 'label');

            $result = [];
            for ($i = 0; $i < $days; $i++) {
                $d        = $from->copy()->addDays($i)->toDateString();
                $result[] = ['label' => Carbon::parse($d)->format('M j'), 'total' => (float) ($rows[$d] ?? 0)];
            }
            return $result;
        }

        $rows = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as label, SUM(commission_amount) as total")
            ->groupBy('label')
            ->pluck('total', 'label');

        $result = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor <= Carbon::now()) {
            $key      = $cursor->format('Y-m');
            $result[] = ['label' => $cursor->format('M Y'), 'total' => (float) ($rows[$key] ?? 0)];
            $cursor->addMonth();
        }
        return $result;
    }

    public function bestMonth(mixed $affiliateIds): ?object
    {
        return AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(commission_amount) as total")
            ->groupBy('month')
            ->orderByDesc('total')
            ->first();
    }

    public function byComm(mixed $affiliateIds): Collection
    {
        return AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->select('affiliate_id', DB::raw('SUM(commission_amount) as total'))
            ->with('affiliate.community:id,name')
            ->groupBy('affiliate_id')
            ->get()
            ->map(fn ($c) => ['community' => $c->affiliate->community->name, 'total' => (float) $c->total])
            ->sortByDesc('total')
            ->values();
    }
}
