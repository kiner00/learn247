<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AffiliateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $period = $request->get('period', 'month');

        $affiliates = Affiliate::where('user_id', $user->id)
            ->with('community:id,name,slug')
            ->latest()
            ->get()
            ->map(fn ($a) => [
                'id'             => $a->id,
                'code'           => $a->code,
                'status'         => $a->status,
                'is_active'      => $a->isActive(),
                'total_earned'   => (float) $a->total_earned,
                'total_paid'     => (float) $a->total_paid,
                'pending_amount' => (float) $a->pendingAmount(),
                'referral_url'   => url("/ref/{$a->code}"),
                'community'      => [
                    'name' => $a->community->name,
                    'slug' => $a->community->slug,
                ],
            ]);

        $allAffiliateIds = $affiliates->pluck('id');

        $from = match ($period) {
            'week'  => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year'  => Carbon::now()->startOfYear(),
            default => null,
        };

        $base = AffiliateConversion::whereIn('affiliate_id', $allAffiliateIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        $summary = [
            'total_earned'      => (float) (clone $base)->sum('commission_amount'),
            'total_paid'        => (float) (clone $base)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount'),
            'total_pending'     => (float) (clone $base)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount'),
            'total_conversions' => (clone $base)->count(),
        ];

        $conversions = (clone $base)
            ->with(['affiliate.community'])
            ->latest()
            ->limit(50)
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

        return response()->json([
            'affiliates'  => $affiliates,
            'summary'     => $summary,
            'conversions' => $conversions,
            'period'      => $period,
        ]);
    }
}
