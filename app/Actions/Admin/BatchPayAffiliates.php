<?php

namespace App\Actions\Admin;

use App\Actions\Affiliate\DisbursePayout;
use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Models\AffiliateConversion;

/**
 * Pays all (or selected) pending affiliate conversions via Xendit.
 * Returns a result summary the controller can turn into a flash message.
 */
class BatchPayAffiliates
{
    public function __construct(
        private DisbursePayout $disburse,
        private MarkAffiliateConversionPaid $mark,
    ) {}

    /**
     * @param  int[]|null $affiliateIds  null = all pending conversions
     * @return array{paid: int, errors: string[], message: string}
     */
    public function execute(?array $affiliateIds = null): array
    {
        $query = AffiliateConversion::where('status', AffiliateConversion::STATUS_PENDING)
            ->with(['affiliate.user', 'affiliate.community']);

        if ($affiliateIds !== null) {
            $query->whereIn('affiliate_id', $affiliateIds);
        }

        $conversions = $query->get()
            ->filter(fn ($c) => DisbursePayout::supports($c->affiliate->payout_method ?? ''));

        $paid   = 0;
        $errors = [];

        foreach ($conversions as $conversion) {
            try {
                $this->disburse->execute($conversion);
                $this->mark->execute($conversion);
                $paid++;
            } catch (\RuntimeException $e) {
                $errors[] = "Conversion #{$conversion->id}: " . $e->getMessage();
            }
        }

        $noun    = $affiliateIds !== null ? 'selected affiliate conversion(s)' : 'affiliate conversion(s)';
        $message = "Paid {$paid} {$noun}.";
        if ($errors) {
            $message .= ' Errors: ' . implode('; ', $errors);
        }

        return compact('paid', 'errors', 'message');
    }
}
