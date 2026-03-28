<?php

namespace App\Queries\Creator;

use App\Models\Community;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Payout\CalculateEligibility;
use App\Services\Payout\OwnerEarningsCalculator;

class GetCreatorDashboard
{
    public function __construct(
        private CalculateEligibility $eligibility,
        private OwnerEarningsCalculator $earningsCalc,
    ) {}

    public function execute(User $user): array
    {
        $communities = Community::where('owner_id', $user->id)
            ->where('price', '>', 0)
            ->withCount('members')
            ->get()
            ->map(fn (Community $community) => $this->buildCommunityData($community));

        $requestHistory = PayoutRequest::where('user_id', $user->id)
            ->where('type', PayoutRequest::TYPE_OWNER)
            ->with('community:id,name')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($r) => [
                'id'               => $r->id,
                'community_name'   => $r->community?->name,
                'amount'           => (float) $r->amount,
                'status'           => $r->status,
                'rejection_reason' => $r->rejection_reason,
                'requested_at'     => $r->created_at->toDateString(),
                'processed_at'     => $r->processed_at?->toDateString(),
            ]);

        return [
            'communities'    => $communities,
            'requestHistory' => $requestHistory,
            'payoutMethod'   => $user->payout_method,
            'payoutDetails'  => $user->payout_details,
            'payoutFee'      => Community::PAYOUT_FEE,
        ];
    }

    private function buildCommunityData(Community $community): array
    {
        [$eligibleNow, $lockedAmount, $nextEligibleDate] = $this->eligibility->forOwner($community);

        $e = $this->earningsCalc->forCommunity($community);

        $pendingRequest = PayoutRequest::where('community_id', $community->id)
            ->where('type', PayoutRequest::TYPE_OWNER)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->latest()
            ->first();

        $recentPayments = Payment::where('community_id', $community->id)
            ->where('status', Payment::STATUS_PAID)
            ->with('user:id,name,email,phone')
            ->latest('paid_at')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'member_name'  => $p->user?->name,
                'member_email' => $p->user?->email,
                'member_phone' => $p->user?->phone,
                'amount'       => (float) $p->amount,
                'paid_at'      => $p->paid_at?->toDateString(),
            ]);

        $abandonedPayments = Subscription::where('community_id', $community->id)
            ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_EXPIRED])
            ->with('user:id,name,email,phone')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($s) => [
                'name'   => $s->user?->name,
                'email'  => $s->user?->email,
                'phone'  => $s->user?->phone,
                'status' => $s->status,
                'date'   => $s->created_at->toDateString(),
            ]);

        return [
            'community_id'       => $community->id,
            'community_name'     => $community->name,
            'community_slug'     => $community->slug,
            'members_count'      => $community->members_count,
            'gross'              => $e['gross'],
            'platform_fee'       => $e['platform_fee'],
            'platform_fee_rate'  => $e['platform_fee_rate'],
            'payout_fee'         => Community::PAYOUT_FEE,
            'commissions'        => $e['affiliate_commission'],
            'earned'             => $e['earned'],
            'paid'               => $e['paid'],
            'eligible_now'       => $eligibleNow,
            'locked_amount'      => $lockedAmount,
            'next_eligible_date' => $nextEligibleDate,
            'pending_request'    => $pendingRequest
                ? ['id' => $pendingRequest->id, 'amount' => (float) $pendingRequest->amount, 'status' => $pendingRequest->status]
                : null,
            'recent_payments'    => $recentPayments,
            'abandoned_payments' => $abandonedPayments,
        ];
    }
}
