<?php

namespace App\Actions\Admin;

use App\Models\Affiliate;
use App\Models\OwnerPayout;
use App\Models\PayoutRequest;
use App\Services\XenditService;
use RuntimeException;

/**
 * Approves a payout request: sends funds via Xendit, records OwnerPayout
 * (if owner type), and marks the request as approved.
 *
 * Throws RuntimeException on invalid payout method or Xendit failure.
 */
class ApprovePayoutRequest
{
    public function __construct(private XenditService $xendit) {}

    public function execute(PayoutRequest $payoutRequest): void
    {
        abort_unless($payoutRequest->isPending(), 422, 'Request is no longer pending.');

        $user = $payoutRequest->user;

        if ($payoutRequest->type === PayoutRequest::TYPE_OWNER) {
            $payoutMethod  = $user->payout_method;
            $payoutDetails = $user->payout_details;
            $holderName    = $user->name;
        } else {
            $affiliate     = Affiliate::findOrFail($payoutRequest->affiliate_id);
            $payoutMethod  = $affiliate->payout_method;
            $payoutDetails = $affiliate->payout_details;
            $holderName    = $user->name;
        }

        if (! in_array($payoutMethod, ['gcash', 'maya']) || ! $payoutDetails) {
            abort(422, 'User has no valid payout method on file.');
        }

        $channelCode = $payoutMethod === 'gcash' ? 'PH_GCASH' : 'PH_PAYMAYA';
        $referenceId = 'req-' . $payoutRequest->id . '-' . time();

        $result = $this->xendit->createPayout([
            'reference_id'       => $referenceId,
            'currency'           => 'PHP',
            'channel_code'       => $channelCode,
            'channel_properties' => [
                'account_holder_name' => $holderName,
                'account_number'      => $payoutDetails,
            ],
            'amount'      => (float) $payoutRequest->amount,
            'description' => "Payout request #{$payoutRequest->id}",
        ]);

        if ($payoutRequest->type === PayoutRequest::TYPE_OWNER) {
            OwnerPayout::create([
                'community_id'     => $payoutRequest->community_id,
                'user_id'          => $user->id,
                'amount'           => $payoutRequest->amount,
                'status'           => 'accepted',
                'xendit_reference' => $result['id'] ?? $referenceId,
                'paid_at'          => now(),
            ]);
        }

        $payoutRequest->update([
            'status'           => PayoutRequest::STATUS_APPROVED,
            'xendit_reference' => $result['id'] ?? $referenceId,
            'processed_at'     => now(),
        ]);
    }
}
