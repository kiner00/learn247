<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Queries\Affiliate\GetAffiliateStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function index(Request $request, GetAffiliateStats $query): JsonResponse
    {
        $user   = $request->user();
        $period = $request->get('period', 'month');

        $affiliates = $query->getAffiliates($user);

        $affiliatesMapped = $affiliates->map(fn ($a) => [
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

        $affiliateIds = $affiliates->pluck('id');
        $summary      = $query->summary($affiliateIds, $period);
        $conversions  = $query->conversions($affiliateIds, $period);

        return response()->json([
            'affiliates'  => $affiliatesMapped,
            'summary'     => $summary,
            'conversions' => $conversions,
            'period'      => $period,
        ]);
    }
}
