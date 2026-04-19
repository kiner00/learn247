<?php

namespace App\Actions\Affiliate;

use App\Models\AffiliateConversion;
use App\Services\XenditService;
use App\Support\PayoutChannelMap;

class DisbursePayout
{
    public function __construct(private XenditService $xendit) {}

    public static function supports(string $payoutMethod): bool
    {
        return PayoutChannelMap::supports($payoutMethod);
    }

    public function execute(AffiliateConversion $conversion): array
    {
        $affiliate = $conversion->affiliate->load('user', 'community');
        $channelCode = PayoutChannelMap::resolve($affiliate->user->payout_method);

        return $this->xendit->createPayout([
            'reference_id' => 'payout-'.$conversion->id.'-'.time(),
            'currency' => 'PHP',
            'channel_code' => $channelCode,
            'channel_properties' => [
                'account_holder_name' => $affiliate->user->name,
                'account_number' => $affiliate->user->payout_details,
            ],
            'amount' => (float) $conversion->commission_amount,
            'description' => "Affiliate commission – {$affiliate->community->name}",
        ]);
    }
}
