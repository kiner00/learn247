<?php

namespace App\Queries\Affiliate;

use App\Actions\Affiliate\DisbursePayout;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Subscription;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class GetAffiliateDashboard
{
    public function execute(Community $community): array
    {
        return Cache::remember(
            CacheKeys::affiliateDashboard($community->id),
            CacheKeys::TTL_ANALYTICS,
            function () use ($community) {
                $affiliates = Affiliate::where('community_id', $community->id)
                    ->with('user')
                    ->latest()
                    ->get()
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'code' => $a->code,
                        'status' => $a->status,
                        'total_earned' => $a->total_earned,
                        'total_paid' => $a->total_paid,
                        'pending_amount' => $a->pendingAmount(),
                        'referral_url' => url("/ref/{$a->code}"),
                        'user' => ['name' => $a->user->name, 'email' => $a->user->email],
                        'payout_method' => $a->user->payout_method,
                        'payout_details' => $a->user->payout_details,
                    ]);

                $conversions = AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id))
                    ->with(['affiliate.user', 'referredUser'])
                    ->latest()
                    ->get()
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'date' => $c->created_at->format('M j, Y'),
                        'referred_user' => $c->referredUser->name,
                        'referred_email' => $c->referredUser->email,
                        'referred_phone' => $c->referredUser->phone,
                        'affiliate_name' => $c->affiliate->user->name,
                        'sale_amount' => $c->sale_amount,
                        'platform_fee' => $c->platform_fee,
                        'commission_amount' => $c->commission_amount,
                        'creator_amount' => $c->creator_amount,
                        'status' => $c->status,
                        'paid_at' => $c->paid_at?->format('M j, Y'),
                        'payout_method' => $c->affiliate->user->payout_method,
                        'can_disburse' => DisbursePayout::supports($c->affiliate->user->payout_method ?? ''),
                    ]);

                $affiliateIds = Affiliate::where('community_id', $community->id)->pluck('id');
                $abandonedLeads = Subscription::whereIn('affiliate_id', $affiliateIds)
                    ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_EXPIRED])
                    ->with('user:id,name,email,phone', 'affiliate.user:id,name')
                    ->latest()
                    ->take(50)
                    ->get()
                    ->map(fn ($s) => [
                        'date' => $s->created_at->format('M j, Y'),
                        'name' => $s->user?->name,
                        'email' => $s->user?->email,
                        'phone' => $s->user?->phone,
                        'affiliate_name' => $s->affiliate?->user?->name,
                        'status' => $s->status,
                    ]);

                $stats = [
                    'total_affiliates' => $affiliates->count(),
                    'total_commissions' => $affiliates->sum('total_earned'),
                    'total_paid_out' => $affiliates->sum('total_paid'),
                ];

                return compact('affiliates', 'conversions', 'stats', 'abandonedLeads');
            }
        );
    }
}
