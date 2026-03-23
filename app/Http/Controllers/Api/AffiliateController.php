<?php

namespace App\Http\Controllers\Api;

use App\Actions\Affiliate\JoinAffiliate;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Community;
use App\Queries\Affiliate\GetAffiliateStats;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function index(Request $request, GetAffiliateStats $query, CalculateEligibility $eligibility): JsonResponse
    {
        $user   = $request->user();
        $period = $request->get('period', 'month');

        $affiliates   = $query->mapForDashboard($user, $eligibility);
        $affiliateIds = $affiliates->pluck('id');
        $summary      = $query->summary($affiliateIds, $period);
        $conversions  = $query->conversions($affiliateIds, $period);

        return response()->json([
            'affiliates'  => $affiliates,
            'summary'     => $summary,
            'conversions' => $conversions,
            'period'      => $period,
        ]);
    }

    public function store(Request $request, Community $community, JoinAffiliate $action): JsonResponse
    {
        $action->execute($request->user(), $community);

        return response()->json(['message' => 'You are now an affiliate!'], 201);
    }

    public function updatePayout(Request $request, Affiliate $affiliate): JsonResponse
    {
        abort_unless($affiliate->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'payout_method'  => ['required', 'string', 'in:gcash,bank,paypal,maya'],
            'payout_details' => ['required', 'string', 'max:255'],
        ]);

        $affiliate->update($data);

        return response()->json(['message' => 'Payout details saved.']);
    }
}
