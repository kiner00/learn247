<?php

namespace App\Actions\Billing\WebhookHandlers;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Actions\Billing\SendChaChing;
use App\Contracts\WebhookHandler;
use App\Models\CurzzoPurchase;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class HandleCurzzoPurchasePaid implements WebhookHandler
{
    private ?CurzzoPurchase $purchase = null;

    public function __construct(
        private readonly RecordAffiliateConversion $recordConversion,
        private readonly SendChaChing $sendChaChing,
    ) {}

    public function matches(string $xenditId): bool
    {
        $this->purchase = CurzzoPurchase::with(['curzzo.community.owner', 'affiliate.user'])
            ->where('xendit_id', $xenditId)
            ->first();

        return $this->purchase !== null;
    }

    public function handle(array $payload, string $eventId, string $status): void
    {
        $purchase = $this->purchase;

        $paymentStatus = $this->mapPaymentStatus($status);
        if ($paymentStatus !== Payment::STATUS_PAID) {
            return;
        }

        try {
            $isMonthly = $purchase->curzzo?->billing_type === 'monthly';
            $expiresAt = $isMonthly
                ? ($purchase->expires_at?->isFuture() ? $purchase->expires_at->addMonth() : now()->addMonth())
                : null;

            $purchase->update([
                'status' => CurzzoPurchase::STATUS_PAID,
                'paid_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            Log::info('Xendit webhook: curzzo purchase paid', [
                'purchase_id' => $purchase->id,
                'monthly' => $isMonthly,
            ]);

            $conversion = $this->recordConversion->executeForCurzzo($purchase);

            if ($conversion) {
                $community = $purchase->curzzo->community;
                $this->sendChaChing->execute(
                    affiliateUser: $purchase->affiliate->user,
                    creator: $community->owner,
                    community: $community,
                    saleAmount: $conversion['sale_amount'],
                    commission: $conversion['commission'],
                    referredBy: $purchase->affiliate->user->name,
                );
            }
        } catch (\Throwable $e) {
            Log::error('HandleCurzzoPurchasePaid failed', [
                'purchase_id' => $purchase->id,
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
