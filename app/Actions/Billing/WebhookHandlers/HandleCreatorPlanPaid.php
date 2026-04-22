<?php

namespace App\Actions\Billing\WebhookHandlers;

use App\Contracts\WebhookHandler;
use App\Models\CreatorSubscription;
use App\Models\Payment;
use App\Services\XenditService;
use Illuminate\Support\Facades\Log;

class HandleCreatorPlanPaid implements WebhookHandler
{
    private ?CreatorSubscription $creatorSub = null;

    public function matches(string $xenditId): bool
    {
        $this->creatorSub = CreatorSubscription::with('user')
            ->where('xendit_id', $xenditId)
            ->first();

        return $this->creatorSub !== null;
    }

    public function handle(array $payload, string $eventId, string $status): void
    {
        $creatorSub = $this->creatorSub;

        try {
            $newStatus = match (strtoupper($status)) {
                'PAID', 'SETTLED' => CreatorSubscription::STATUS_ACTIVE,
                'EXPIRED' => CreatorSubscription::STATUS_EXPIRED,
                'FAILED' => CreatorSubscription::STATUS_CANCELLED,
                default => CreatorSubscription::STATUS_PENDING,
            };

            if ($newStatus === CreatorSubscription::STATUS_ACTIVE) {
                $base = $creatorSub->expires_at && $creatorSub->expires_at->isFuture()
                    ? $creatorSub->expires_at
                    : now();
                $expiresAt = $creatorSub->isAnnual() ? $base->copy()->addYear() : $base->copy()->addMonth();

                $creatorSub->update(['status' => $newStatus, 'expires_at' => $expiresAt]);

                // Record idempotency payment row so this event isn't re-processed
                $csGross = (float) ($payload['amount'] ?? 0);
                $csChannel = $payload['payment_channel'] ?? '';
                $csProcessingFee = XenditService::collectionFee($csChannel, $csGross);

                Payment::create([
                    'subscription_id' => null,
                    'community_id' => null,
                    'user_id' => $creatorSub->user_id,
                    'amount' => $csGross,
                    'processing_fee' => $csProcessingFee,
                    'platform_fee' => 0,
                    'currency' => $payload['currency'] ?? 'PHP',
                    'status' => Payment::STATUS_PAID,
                    'provider_reference' => $payload['payment_id'] ?? ($payload['external_id'] ?? null),
                    'xendit_event_id' => $eventId,
                    'metadata' => $payload,
                    'paid_at' => now(),
                ]);

                Log::info('Xendit webhook: creator plan activated', ['user_id' => $creatorSub->user_id]);
            } else {
                $creatorSub->update(['status' => $newStatus]);
                Log::info('Xendit webhook: creator plan status updated', [
                    'status' => $newStatus,
                    'user_id' => $creatorSub->user_id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('HandleCreatorPlanPaid failed', [
                'user_id' => $creatorSub->user_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
