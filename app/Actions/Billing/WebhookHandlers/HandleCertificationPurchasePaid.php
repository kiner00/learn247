<?php

namespace App\Actions\Billing\WebhookHandlers;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Actions\Billing\SendChaChing;
use App\Contracts\WebhookHandler;
use App\Models\CertificationPurchase;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class HandleCertificationPurchasePaid implements WebhookHandler
{
    private ?CertificationPurchase $certPurchase = null;

    public function __construct(
        private readonly RecordAffiliateConversion $recordConversion,
        private readonly SendChaChing $sendChaChing,
    ) {}

    public function matches(string $xenditId): bool
    {
        $this->certPurchase = CertificationPurchase::with(['certification.community', 'affiliate.user'])
            ->where('xendit_id', $xenditId)
            ->first();

        return $this->certPurchase !== null;
    }

    public function handle(array $payload, string $eventId, string $status): void
    {
        $certPurchase = $this->certPurchase;

        $paymentStatus = $this->mapPaymentStatus($status);
        if ($paymentStatus !== Payment::STATUS_PAID) {
            return;
        }

        try {
            $certPurchase->update([
                'status' => CertificationPurchase::STATUS_PAID,
                'paid_at' => now(),
            ]);

            Log::info('Xendit webhook: certification purchase paid', [
                'purchase_id' => $certPurchase->id,
            ]);

            // Record affiliate commission
            $conversion = $this->recordConversion->executeForCertification($certPurchase);

            if ($conversion) {
                $this->sendChaChing->execute(
                    affiliateUser: $certPurchase->affiliate->user,
                    creator: $certPurchase->certification->community->owner,
                    community: $certPurchase->certification->community,
                    saleAmount: $conversion['sale_amount'],
                    commission: $conversion['commission'],
                    referredBy: $certPurchase->affiliate->user->name,
                );
            }
        } catch (\Throwable $e) {
            Log::error('HandleCertificationPurchasePaid failed', [
                'purchase_id' => $certPurchase->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function mapPaymentStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID', 'SETTLED' => Payment::STATUS_PAID,
            'EXPIRED' => Payment::STATUS_EXPIRED,
            'FAILED' => Payment::STATUS_FAILED,
            default => Payment::STATUS_PENDING,
        };
    }
}
