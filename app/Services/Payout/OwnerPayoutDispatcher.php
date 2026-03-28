<?php

namespace App\Services\Payout;

use App\Models\Community;
use App\Models\OwnerPayout;
use App\Services\XenditService;
use App\Support\PayoutChannelMap;
use RuntimeException;

/**
 * Orchestrates a single owner payout: calculates pending earnings, sends via
 * Xendit, and records the OwnerPayout row. Extracted from AdminController so
 * payOwner / batchPayOwners / paySelectedOwners all share the same logic.
 *
 * Throws RuntimeException if there is nothing to pay or the Xendit call fails.
 */
class OwnerPayoutDispatcher
{
    public function __construct(
        private OwnerEarningsCalculator $calculator,
        private XenditService $xendit,
    ) {}

    /**
     * @return array{amount: float, reference: string}
     * @throws RuntimeException
     */
    public function dispatch(Community $community): array
    {
        $owner    = $community->owner;
        $earnings = $this->calculator->forCommunity($community);
        $pending  = $earnings['pending'];

        if ($pending <= 0) {
            throw new RuntimeException('No pending amount for this community.');
        }

        $disbursementAmount = round($pending - Community::PAYOUT_FEE, 2);

        if ($disbursementAmount <= 0) {
            throw new RuntimeException('Pending amount must exceed the ₱' . Community::PAYOUT_FEE . ' processing fee.');
        }

        $channelCode = PayoutChannelMap::resolve($owner->payout_method);
        $referenceId = 'owner-' . $community->id . '-' . time();

        $result = $this->xendit->createPayout([
            'reference_id'       => $referenceId,
            'currency'           => 'PHP',
            'channel_code'       => $channelCode,
            'channel_properties' => [
                'account_holder_name' => $owner->name,
                'account_number'      => $owner->payout_details,
            ],
            'amount'      => $disbursementAmount,
            'description' => "Owner earnings – {$community->name}",
        ]);

        OwnerPayout::create([
            'community_id'     => $community->id,
            'user_id'          => $owner->id,
            'amount'           => $pending,
            'status'           => 'accepted',
            'xendit_reference' => $result['id'] ?? $referenceId,
            'paid_at'          => now(),
        ]);

        return ['amount' => $pending, 'reference' => $result['id'] ?? $referenceId];
    }

    public function canDispatch(Community $community): bool
    {
        $owner = $community->owner;
        return PayoutChannelMap::supports($owner->payout_method) && $owner->payout_details;
    }

    /**
     * Dispatch payouts to a collection of communities, skipping ineligible ones.
     * Returns a result summary the controller can turn into a flash message.
     *
     * @return array{paid: int, errors: string[], message: string}
     */
    public function batchDispatch(\Illuminate\Support\Collection $communities, string $label = 'community owner(s)'): array
    {
        $paid   = 0;
        $errors = [];

        foreach ($communities as $community) {
            if (! $this->canDispatch($community)) {
                continue;
            }
            try {
                $this->dispatch($community);
                $paid++;
            } catch (RuntimeException $e) {
                if ($e->getMessage() !== 'No pending amount for this community.') {
                    $errors[] = "{$community->name}: " . $e->getMessage();
                }
            }
        }

        $message = "Paid {$paid} {$label}.";
        if ($errors) {
            $message .= ' Errors: ' . implode('; ', $errors);
        }

        return compact('paid', 'errors', 'message');
    }
}
