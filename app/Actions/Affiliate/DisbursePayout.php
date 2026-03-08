<?php

namespace App\Actions\Affiliate;

use App\Models\AffiliateConversion;
use App\Services\XenditService;

class DisbursePayout
{
    private const CHANNEL_MAP = [
        'gcash' => 'PH_GCASH',
        'maya'  => 'PH_PAYMAYA',
    ];

    public function __construct(private XenditService $xendit) {}

    public static function supports(string $payoutMethod): bool
    {
        return isset(self::CHANNEL_MAP[$payoutMethod]);
    }

    public function execute(AffiliateConversion $conversion): array
    {
        $affiliate   = $conversion->affiliate->load('user', 'community');
        $channelCode = self::CHANNEL_MAP[$affiliate->user->payout_method];

        return $this->xendit->createPayout([
            'reference_id'       => 'payout-' . $conversion->id . '-' . time(),
            'currency'           => 'PHP',
            'channel_code'       => $channelCode,
            'channel_properties' => [
                'account_holder_name' => $affiliate->user->name,
                'account_number'      => $affiliate->user->payout_details,
            ],
            'amount'      => (float) $conversion->commission_amount,
            'description' => "Affiliate commission – {$affiliate->community->name}",
        ]);
    }
}
