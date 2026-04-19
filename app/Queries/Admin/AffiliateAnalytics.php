<?php

namespace App\Queries\Admin;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\PayoutRequest;
use Illuminate\Support\Facades\DB;

class AffiliateAnalytics
{
    public function execute(string $search = '', string $status = ''): array
    {
        $affiliates = Affiliate::with(['user', 'community'])
            ->withCount('conversions')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('community', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->get();

        // Bulk-fetch conversion aggregates per affiliate
        $conversionStats = AffiliateConversion::select(
            'affiliate_id',
            DB::raw('COUNT(*) as total_conversions'),
            DB::raw('SUM(sale_amount) as total_sales'),
            DB::raw('SUM(commission_amount) as total_commission'),
            DB::raw('SUM(CASE WHEN status = "paid" THEN commission_amount ELSE 0 END) as commission_paid'),
            DB::raw('SUM(CASE WHEN status = "pending" THEN commission_amount ELSE 0 END) as commission_pending'),
        )
            ->groupBy('affiliate_id')
            ->get()
            ->keyBy('affiliate_id');

        // Bulk-fetch in-flight payout requests per affiliate
        $inFlight = PayoutRequest::where('type', PayoutRequest::TYPE_AFFILIATE)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->select('affiliate_id', DB::raw('SUM(amount) as total'))
            ->groupBy('affiliate_id')
            ->get()
            ->keyBy('affiliate_id');

        $rows = [];
        $totals = [
            'conversions' => 0,
            'total_sales' => 0,
            'total_commission' => 0,
            'commission_paid' => 0,
            'commission_pending' => 0,
            'in_flight' => 0,
        ];

        foreach ($affiliates as $affiliate) {
            $stats = $conversionStats->get($affiliate->id);
            $flight = (float) ($inFlight->get($affiliate->id)?->total ?? 0);

            $totalSales = (float) ($stats->total_sales ?? 0);
            $totalCommission = (float) ($stats->total_commission ?? 0);
            $commissionPaid = (float) ($stats->commission_paid ?? 0);
            $commissionPending = (float) ($stats->commission_pending ?? 0);
            $availableNow = max(0, round($commissionPending - $flight, 2));

            $row = [
                'affiliate_id' => $affiliate->id,
                'affiliate_code' => $affiliate->code,
                'affiliate_status' => $affiliate->status,
                'payout_method' => $affiliate->payout_method,
                'user_name' => $affiliate->user?->name ?? '—',
                'user_email' => $affiliate->user?->email ?? '—',
                'community_name' => $affiliate->community?->name ?? '—',
                'community_slug' => $affiliate->community?->slug ?? '',
                'conversions' => (int) ($stats->total_conversions ?? 0),
                'total_sales' => $totalSales,
                'total_commission' => $totalCommission,
                'commission_paid' => $commissionPaid,
                'commission_pending' => $commissionPending,
                'in_flight' => $flight,
                'available_now' => $availableNow,
            ];

            $rows[] = $row;

            $totals['conversions'] += $row['conversions'];
            $totals['total_sales'] += $totalSales;
            $totals['total_commission'] += $totalCommission;
            $totals['commission_paid'] += $commissionPaid;
            $totals['commission_pending'] += $commissionPending;
            $totals['in_flight'] += $flight;
        }

        foreach ($totals as $k => $v) {
            $totals[$k] = is_float($v) ? round($v, 2) : $v;
        }

        return [
            'affiliates' => $rows,
            'totals' => $totals,
            'filters' => ['search' => $search, 'status' => $status],
        ];
    }
}
