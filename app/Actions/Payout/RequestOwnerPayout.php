<?php

namespace App\Actions\Payout;

use App\Models\Community;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Queries\Payout\CalculateEligibility;
use App\Support\CacheKeys;

class RequestOwnerPayout
{
    public function __construct(private CalculateEligibility $eligibility) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(User $owner, Community $community, float $amount): array
    {
        if (! $owner->isKycVerified()) {
            return ['success' => false, 'message' => 'KYC verification is required before requesting payouts. Please complete identity verification in Account Settings.'];
        }

        if (! in_array($owner->payout_method, ['gcash', 'maya']) || ! $owner->payout_details) {
            return ['success' => false, 'message' => 'Please set your payout method in Account Settings before requesting a payout.'];
        }

        $hasPending = PayoutRequest::where('community_id', $community->id)
            ->where('type', PayoutRequest::TYPE_OWNER)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->exists();

        if ($hasPending) {
            return ['success' => false, 'message' => 'You already have a pending or approved payout request for this community.'];
        }

        [$eligibleNow] = $this->eligibility->forOwner($community);

        if ($eligibleNow <= 0) {
            return ['success' => false, 'message' => 'No eligible earnings yet. Payments must be at least 15 days old.'];
        }

        if ($amount > $eligibleNow) {
            return ['success' => false, 'message' => "Amount exceeds eligible balance of {$eligibleNow}."];
        }

        if ($amount <= Community::PAYOUT_FEE) {
            return ['success' => false, 'message' => 'Minimum payout amount is ₱' . (Community::PAYOUT_FEE + 1) . ' (must exceed the ₱' . Community::PAYOUT_FEE . ' processing fee).'];
        }

        PayoutRequest::create([
            'user_id'         => $owner->id,
            'type'            => PayoutRequest::TYPE_OWNER,
            'community_id'    => $community->id,
            'amount'          => $amount,
            'eligible_amount' => $eligibleNow,
            'status'          => PayoutRequest::STATUS_PENDING,
        ]);

        CacheKeys::flushCreator($owner->id);
        CacheKeys::flushAdmin();

        return ['success' => true, 'message' => 'Payout request submitted. The admin will review and process it shortly.'];
    }
}
