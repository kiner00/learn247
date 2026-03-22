<?php

namespace App\Actions\Payout;

use App\Models\Affiliate;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Queries\Payout\CalculateEligibility;

class RequestAllAffiliatePayouts
{
    public function __construct(private CalculateEligibility $eligibility) {}

    /**
     * Submit payout requests for all eligible affiliates belonging to a user.
     * Returns ['success' => bool, 'message' => string].
     */
    public function execute(User $user): array
    {
        $affiliates = Affiliate::where('user_id', $user->id)
            ->where('status', Affiliate::STATUS_ACTIVE)
            ->whereIn('payout_method', ['gcash', 'maya'])
            ->whereNotNull('payout_details')
            ->get();

        if ($affiliates->isEmpty()) {
            return ['success' => false, 'message' => 'No affiliates with a valid payout method set.'];
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

            $eligibleNow = $this->eligibility->forAffiliate($affiliate);

            if ($eligibleNow <= 0) {
                continue;
            }

            PayoutRequest::create([
                'user_id'         => $affiliate->user_id,
                'type'            => PayoutRequest::TYPE_AFFILIATE,
                'community_id'    => $affiliate->community_id,
                'affiliate_id'    => $affiliate->id,
                'amount'          => $eligibleNow,
                'eligible_amount' => $eligibleNow,
                'status'          => PayoutRequest::STATUS_PENDING,
            ]);

            $submitted++;
        }

        if ($submitted === 0) {
            return ['success' => false, 'message' => 'No eligible affiliate earnings to request payout for.'];
        }

        return ['success' => true, 'message' => "Payout request submitted for {$submitted} affiliate program(s). The admin will review shortly."];
    }
}
