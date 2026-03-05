<?php

namespace App\Actions\Affiliate;

use App\Models\AffiliateConversion;

class MarkAffiliateConversionPaid
{
    public function execute(AffiliateConversion $conversion): void
    {
        $conversion->update([
            'status'  => AffiliateConversion::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $conversion->affiliate->increment('total_paid', (float) $conversion->commission_amount);
    }
}
