<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payout\RequestAffiliatePayout;
use App\Actions\Payout\RequestOwnerPayout;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\PayoutRequest;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutRequestController extends Controller
{
    public function storeOwner(Request $request, Community $community, RequestOwnerPayout $action): JsonResponse
    {
        abort_unless($community->owner_id === $request->user()->id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $result = $action->execute($request->user(), $community, (float) $validated['amount']);

        return response()->json(['message' => $result['message']], $result['success'] ? 201 : 422);
    }

    public function storeAffiliate(Request $request, Affiliate $affiliate, RequestAffiliatePayout $action): JsonResponse
    {
        abort_unless($affiliate->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $result = $action->execute($affiliate, (float) $validated['amount']);

        return response()->json(['message' => $result['message']], $result['success'] ? 201 : 422);
    }

    public function storeAffiliateAll(Request $request, CalculateEligibility $eligibility): JsonResponse
    {
        $user = $request->user();

        $affiliates = Affiliate::where('user_id', $user->id)
            ->where('status', Affiliate::STATUS_ACTIVE)
            ->whereIn('payout_method', ['gcash', 'maya'])
            ->whereNotNull('payout_details')
            ->get();

        if ($affiliates->isEmpty()) {
            return response()->json(['message' => 'No affiliates with a valid payout method set.'], 422);
        }

        $submitted = 0;
        foreach ($affiliates as $affiliate) {
            $hasPending = PayoutRequest::where('affiliate_id', $affiliate->id)
                ->where('type', PayoutRequest::TYPE_AFFILIATE)
                ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
                ->exists();

            if ($hasPending) {
                continue;
            }

            $eligibleNow = $eligibility->forAffiliate($affiliate);
            if ($eligibleNow <= 0) {
                continue;
            }

            PayoutRequest::create([
                'user_id' => $affiliate->user_id,
                'type' => PayoutRequest::TYPE_AFFILIATE,
                'community_id' => $affiliate->community_id,
                'affiliate_id' => $affiliate->id,
                'amount' => $eligibleNow,
                'eligible_amount' => $eligibleNow,
                'status' => PayoutRequest::STATUS_PENDING,
            ]);

            $submitted++;
        }

        if ($submitted === 0) {
            return response()->json(['message' => 'No eligible affiliate earnings to request payout for.'], 422);
        }

        return response()->json(['message' => "Payout request submitted for {$submitted} affiliate program(s)."], 201);
    }
}
