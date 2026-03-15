<?php

namespace App\Queries\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetAffiliateStats
{
    public function getAffiliates(User $user): Collection
    {
        return Affiliate::where('user_id', $user->id)
            ->with('community:id,name,slug')
            ->latest()
            ->get();
    }

    /**
     * @return array{total_earned: float, total_paid: float, total_pending: float, total_conversions: int}
     */
    public function summary(Collection $affiliateIds, ?string $period = 'month'): array
    {
        $from = $this->periodStart($period);

        $base = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        return [
            'total_earned'      => (float) (clone $base)->sum('commission_amount'),
            'total_paid'        => (float) (clone $base)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount'),
            'total_pending'     => (float) (clone $base)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount'),
            'total_conversions' => (clone $base)->count(),
        ];
    }

    public function conversions(Collection $affiliateIds, ?string $period = 'month', int $limit = 50): Collection
    {
        $from = $this->periodStart($period);

        return AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->with(['affiliate.community'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'date'              => $c->created_at->toDateString(),
                'community'         => $c->affiliate->community->name,
                'sale_amount'       => (float) $c->sale_amount,
                'commission_amount' => (float) $c->commission_amount,
                'status'            => $c->status,
                'paid_at'           => $c->paid_at?->toDateString(),
            ]);
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
