<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorController extends Controller
{
    public function dashboard(Request $request, CalculateEligibility $eligibility): JsonResponse
    {
        $user = $request->user();

        $communities = Community::where('owner_id', $user->id)
            ->where('price', '>', 0)
            ->withCount('members')
            ->get()
            ->map(function (Community $community) use ($eligibility) {
                [$eligibleNow, $lockedAmount, $nextEligibleDate] = $eligibility->forOwner($community);

                $gross = (float) Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)->sum('amount');
                $affiliateCommission = (float) AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('community_id', $community->id))->sum('commission_amount');
                $platformFee = round($gross * $community->platformFeeRate(), 2);
                $earned      = round($gross - $platformFee - $affiliateCommission, 2);
                $paid        = (float) OwnerPayout::where('community_id', $community->id)->where('status', '!=', 'failed')->sum('amount');

                $pendingRequest = PayoutRequest::where('community_id', $community->id)
                    ->where('type', PayoutRequest::TYPE_OWNER)->where('status', PayoutRequest::STATUS_PENDING)->first();

                $recentPayments = Payment::where('community_id', $community->id)->where('status', Payment::STATUS_PAID)
                    ->with('user:id,name,email')->latest('paid_at')->take(10)->get()
                    ->map(fn ($p) => ['member_name' => $p->user?->name, 'member_email' => $p->user?->email, 'amount' => (float) $p->amount, 'paid_at' => $p->paid_at?->toDateString()]);

                return [
                    'community_id'       => $community->id,
                    'community_name'     => $community->name,
                    'community_slug'     => $community->slug,
                    'members_count'      => $community->members_count,
                    'gross'              => $gross,
                    'platform_fee'       => $platformFee,
                    'commissions'        => $affiliateCommission,
                    'earned'             => $earned,
                    'paid'               => $paid,
                    'eligible_now'       => $eligibleNow,
                    'locked_amount'      => $lockedAmount,
                    'next_eligible_date' => $nextEligibleDate,
                    'pending_request'    => $pendingRequest ? ['id' => $pendingRequest->id, 'amount' => (float) $pendingRequest->amount] : null,
                    'recent_payments'    => $recentPayments,
                ];
            });

        $requestHistory = PayoutRequest::where('user_id', $user->id)->where('type', PayoutRequest::TYPE_OWNER)
            ->with('community:id,name')->latest()->take(20)->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'community_name' => $r->community?->name, 'amount' => (float) $r->amount,
                'status' => $r->status, 'rejection_reason' => $r->rejection_reason,
                'requested_at' => $r->created_at->toDateString(), 'processed_at' => $r->processed_at?->toDateString(),
            ]);

        return response()->json([
            'communities'    => $communities,
            'requestHistory' => $requestHistory,
            'payoutMethod'   => $user->payout_method,
            'payoutDetails'  => $user->payout_details,
        ]);
    }
}
