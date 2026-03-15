<?php

namespace App\Actions\Payout;

use App\Models\Affiliate;
use App\Models\PayoutRequest;
use App\Queries\Payout\CalculateEligibility;

class RequestAffiliatePayout
{
    public function __construct(private CalculateEligibility $eligibility) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(Affiliate $affiliate, float $amount): array
    {
        if (! $affiliate->isActive()) {
            return ['success' => false, 'message' => 'Your affiliate membership is suspended. Renew your subscription to re-enable payouts.'];
        }

        if (! in_array($affiliate->payout_method, ['gcash', 'maya']) || ! $affiliate->payout_details) {
            return ['success' => false, 'message' => 'Please set your payout method before requesting a payout.'];
        }

        $hasPending = PayoutRequest::where('affiliate_id', $affiliate->id)
            ->where('type', PayoutRequest::TYPE_AFFILIATE)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->exists();

        if ($hasPending) {
            return ['success' => false, 'message' => 'You already have an active payout request for this affiliate program.'];
        }

        $eligibleNow = $this->eligibility->forAffiliate($affiliate);

        if ($eligibleNow <= 0) {
            return ['success' => false, 'message' => 'No eligible earnings yet. Commissions must be at least 15 days old.'];
        }

        if ($amount > $eligibleNow) {
            return ['success' => false, 'message' => "Amount exceeds eligible balance of {$eligibleNow}."];
        }

        PayoutRequest::create([
            'user_id'         => $affiliate->user_id,
            'type'            => PayoutRequest::TYPE_AFFILIATE,
            'community_id'    => $affiliate->community_id,
            'affiliate_id'    => $affiliate->id,
            'amount'          => $amount,
            'eligible_amount' => $eligibleNow,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        return ['success' => true, 'message' => 'Payout request submitted. The admin will review and process it shortly.'];
    }
}
