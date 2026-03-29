<?php

namespace App\Actions\Affiliate;

use App\Models\AffiliateConversion;
use App\Support\CacheKeys;

class MarkAffiliateConversionPaid
{
    public function execute(AffiliateConversion $conversion): void
    {
        $conversion->update([
            'status'  => AffiliateConversion::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $conversion->affiliate->increment('total_paid', (float) $conversion->commission_amount);

        CacheKeys::flushCommunity($conversion->affiliate->community_id);
        CacheKeys::flushAdmin();
    }
}
