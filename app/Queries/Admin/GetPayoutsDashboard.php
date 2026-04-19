<?php

namespace App\Queries\Admin;

use App\Actions\Affiliate\DisbursePayout;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Queries\Payout\CalculateEligibility;
use App\Services\Payout\OwnerEarningsCalculator;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

/**
 * Builds all data needed for the Admin Payouts page.
 * Owners list, affiliates list, payout requests, and summary stats.
 */
class GetPayoutsDashboard
{
    public function __construct(
        private OwnerEarningsCalculator $earnings,
        private CalculateEligibility $eligibility,
    ) {}

    public function execute(): array
    {
        return Cache::remember(
            CacheKeys::adminPayouts(),
            CacheKeys::TTL_ADMIN_DASHBOARD,
            function () {
                $owners = $this->buildOwners();
                $affiliates = $this->buildAffiliates();
                $payoutRequests = $this->buildPayoutRequests();

                $totalPlatformFee = round(
                    Community::with('owner')->whereHas('owner')->get()->sum(function ($c) {
                        $gross = (float) Payment::where('community_id', $c->id)->where('status', Payment::STATUS_PAID)->sum('amount');

                        return $gross * $c->platformFeeRate();
                    }),
                    2
                );

                $stats = [
                    'owners_pending' => $owners->sum('total_pending'),
                    'affiliates_pending' => $affiliates->sum('pending'),
                    'payout_requests_pending' => PayoutRequest::where('status', PayoutRequest::STATUS_PENDING)->count(),
                    'platform_fee_collected' => $totalPlatformFee,
                ];

                return compact('owners', 'affiliates', 'payoutRequests', 'stats');
            }
        );
    }

    private function buildOwners(): \Illuminate\Support\Collection
    {
        return Community::with('owner')
            ->where('price', '>', 0)
            ->whereHas('owner')
            ->get()
            ->groupBy('owner_id')
            ->map(function ($communities) {
                $owner = $communities->first()->owner;

                $rows = $communities->map(function ($community) {
                    $e = $this->earnings->forCommunity($community);
                    [$availablePayout] = $this->eligibility->forOwner($community);

                    return [
                        'community_id' => $community->id,
                        'community_name' => $community->name,
                        'community_slug' => $community->slug,
                        'gross' => $e['gross'],
                        'platform_fee' => $e['platform_fee'],
                        'platform_fee_rate' => $e['platform_fee_rate'],
                        'commissions' => $e['affiliate_commission'],
                        'earned' => $e['earned'],
                        'paid' => $e['paid'],
                        'pending' => $e['pending'],
                        'available_payout' => $availablePayout,
                    ];
                })->values();

                return [
                    'user_id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'payout_method' => $owner->payout_method,
                    'payout_details' => $owner->payout_details,
                    'can_disburse' => in_array($owner->payout_method, ['gcash', 'maya']) && $owner->payout_details,
                    'creator_plan' => $owner->creatorPlan(),
                    'total_earned' => $rows->sum('earned'),
                    'total_paid' => $rows->sum('paid'),
                    'total_pending' => $rows->sum('pending'),
                    'communities' => $rows,
                ];
            })
            ->filter(fn ($o) => $o['total_earned'] > 0)
            ->values();
    }

    private function buildAffiliates(): \Illuminate\Support\Collection
    {
        return Affiliate::with(['user', 'community'])
            ->whereHas('user')
            ->whereHas('community')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->user->name,
                'email' => $a->user->email,
                'community_name' => $a->community->name,
                'total_earned' => (float) $a->total_earned,
                'total_paid' => (float) $a->total_paid,
                'pending' => $a->pendingAmount(),
                'payout_method' => $a->payout_method,
                'payout_details' => $a->payout_details,
                'can_disburse' => DisbursePayout::supports($a->payout_method ?? ''),
            ])
            ->filter(fn ($a) => $a['total_earned'] > 0)
            ->values();
    }

    private function buildPayoutRequests(): \Illuminate\Support\Collection
    {
        return PayoutRequest::with(['user', 'community', 'processedByUser'])
            ->whereHas('user')
            ->latest()
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'user_name' => $r->user->name,
                'user_email' => $r->user->email,
                'payout_method' => $r->type === PayoutRequest::TYPE_OWNER
                    ? $r->user->payout_method
                    : optional(Affiliate::find($r->affiliate_id))->payout_method,
                'payout_details' => $r->type === PayoutRequest::TYPE_OWNER
                    ? $r->user->payout_details
                    : optional(Affiliate::find($r->affiliate_id))->payout_details,
                'type' => $r->type,
                'community_name' => $r->community?->name,
                'amount' => (float) $r->amount,
                'eligible_amount' => (float) $r->eligible_amount,
                'status' => $r->status,
                'rejection_reason' => $r->rejection_reason,
                'xendit_reference' => $r->xendit_reference,
                'requested_at' => $r->created_at->toDateString(),
                'processed_at' => $r->processed_at?->format('M d, Y H:i'),
                'processed_by_name' => $r->processedByUser?->name,
            ]);
    }
}
