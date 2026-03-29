<?php

namespace App\Actions\Admin;

use App\Models\PayoutRequest;
use App\Support\CacheKeys;

class RejectPayoutRequest
{
    public function execute(PayoutRequest $payoutRequest, ?string $reason): void
    {
        abort_unless($payoutRequest->isPending(), 422, 'Request is no longer pending.');

        $payoutRequest->update([
            'status'           => PayoutRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'processed_at'     => now(),
        ]);

        CacheKeys::flushAdmin();
        CacheKeys::flushCreator($payoutRequest->user_id);
    }
}
