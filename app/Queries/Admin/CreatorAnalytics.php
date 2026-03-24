<?php

namespace App\Queries\Admin;

use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class CreatorAnalytics
{
    public function execute(string $search = '', string $plan = ''): array
    {
        $communities = Community::with('owner')
            ->withCount(['members as subscribers_count' => fn ($q) => $q->where('status', 'active')])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('owner', fn ($q) => $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->orderByDesc('id')
            ->get();

        // Bulk-fetch payment aggregates per community
        $paymentStats = Payment::where('status', Payment::STATUS_PAID)
            ->select(
                'community_id',
                DB::raw('SUM(amount) as gross'),
                DB::raw('SUM(processing_fee) as processing_fee'),
                DB::raw('SUM(platform_fee) as platform_fee'),
            )
            ->groupBy('community_id')
            ->get()
            ->keyBy('community_id');

        // Bulk-fetch affiliate commissions per community
        $affiliateCommissions = AffiliateConversion::select(
                'affiliates.community_id',
                DB::raw('SUM(affiliate_conversions.commission_amount) as total')
            )
            ->join('affiliates', 'affiliates.id', '=', 'affiliate_conversions.affiliate_id')
            ->groupBy('affiliates.community_id')
            ->get()
            ->keyBy('community_id');

        // Bulk-fetch already-paid owner payouts per community
        $ownerPaid = OwnerPayout::where('status', '!=', 'failed')
            ->select('community_id', DB::raw('SUM(amount) as total'))
            ->groupBy('community_id')
            ->get()
            ->keyBy('community_id');

        $rows   = [];
        $totals = [
            'gross'                => 0,
            'processing_fee'       => 0,
            'platform_fee'         => 0,
            'net_platform_profit'  => 0,
            'affiliate_commission' => 0,
            'creator_earned'       => 0,
            'creator_paid'         => 0,
            'creator_pending'      => 0,
        ];

        foreach ($communities as $community) {
            $owner = $community->owner;
            if (! $owner) {
                continue;
            }

            $creatorPlan = $owner->creatorPlan();

            // Filter by plan if provided
            if ($plan && $creatorPlan !== $plan) {
                continue;
            }

            $stats     = $paymentStats->get($community->id);
            $gross     = (float) ($stats->gross ?? 0);
            $procFee   = (float) ($stats->processing_fee ?? 0);
            $platFee   = (float) ($stats->platform_fee ?? 0);
            $affComm   = (float) ($affiliateCommissions->get($community->id)?->total ?? 0);
            $paid      = (float) ($ownerPaid->get($community->id)?->total ?? 0);

            $netProfit      = round($platFee - $procFee, 2);
            $creatorEarned  = round($gross - $platFee - $affComm, 2);
            $creatorPending = max(0, round($creatorEarned - $paid, 2));

            $row = [
                'community_id'        => $community->id,
                'community_name'      => $community->name,
                'community_slug'      => $community->slug,
                'community_price'     => (float) $community->price,
                'creator_name'        => $owner->name,
                'creator_email'       => $owner->email,
                'creator_plan'        => $creatorPlan,
                'subscribers'         => $community->subscribers_count,
                'gross'               => $gross,
                'processing_fee'      => $procFee,
                'platform_fee'        => $platFee,
                'net_platform_profit' => $netProfit,
                'affiliate_commission'=> $affComm,
                'creator_earned'      => $creatorEarned,
                'creator_paid'        => $paid,
                'creator_pending'     => $creatorPending,
                'is_profitable'       => $netProfit >= 0,
            ];

            $rows[] = $row;

            $totals['gross']                += $gross;
            $totals['processing_fee']       += $procFee;
            $totals['platform_fee']         += $platFee;
            $totals['net_platform_profit']  += $netProfit;
            $totals['affiliate_commission'] += $affComm;
            $totals['creator_earned']       += $creatorEarned;
            $totals['creator_paid']         += $paid;
            $totals['creator_pending']      += $creatorPending;
        }

        foreach ($totals as $k => $v) {
            $totals[$k] = round($v, 2);
        }

        return [
            'creators' => $rows,
            'totals'   => $totals,
            'filters'  => ['search' => $search, 'plan' => $plan],
        ];
    }
}
