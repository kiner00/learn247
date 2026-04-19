<?php

namespace App\Actions\Billing\WebhookHandlers;

use App\Contracts\WebhookHandler;
use App\Models\CurzzoTopup;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class HandleCurzzoTopupPaid implements WebhookHandler
{
    private ?CurzzoTopup $topup = null;

    public function matches(string $xenditId): bool
    {
        $this->topup = CurzzoTopup::where('xendit_id', $xenditId)->first();

        return $this->topup !== null;
    }

    public function handle(array $payload, string $eventId, string $status): void
    {
        $topup = $this->topup;

        $paymentStatus = $this->mapPaymentStatus($status);
        if ($paymentStatus !== Payment::STATUS_PAID) {
            return;
        }

        try {
            $updateData = [
                'status' => CurzzoTopup::STATUS_PAID,
                'paid_at' => now(),
            ];

            // Day pass (messages=0) gets 24h expiry
            if ($topup->messages === 0) {
                $updateData['expires_at'] = now()->addHours(24);
            }

            $topup->update($updateData);

            Log::info('Xendit webhook: curzzo topup paid', [
                'topup_id' => $topup->id,
                'messages' => $topup->messages,
                'day_pass' => $topup->messages === 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('HandleCurzzoTopupPaid failed', [
                'topup_id' => $topup->id,
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
