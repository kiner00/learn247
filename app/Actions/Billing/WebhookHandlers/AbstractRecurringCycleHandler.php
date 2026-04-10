<?php

namespace App\Actions\Billing\WebhookHandlers;

use App\Models\Payment;
use App\Services\XenditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Template Method pattern — defines the algorithm skeleton for handling
 * recurring cycle webhooks. Subclasses override only the hook methods
 * that differ per entity type.
 */
abstract class AbstractRecurringCycleHandler
{
    /**
     * Check if this handler owns the given plan ID.
     */
    public function matchesPlan(string $planId): bool
    {
        return $this->findEntityByPlanId($planId) !== null;
    }

    /**
     * TEMPLATE METHOD — handles a successful recurring cycle payment.
     * The skeleton is final; subclasses customise via hooks.
     */
    final public function handleCycleSucceeded(array $payload): void
    {
        $planId  = $payload['plan_id'] ?? $payload['id'] ?? null;
        $cycleId = $payload['cycle_id'] ?? $payload['id'] ?? null;
        $entity  = $this->findEntityByPlanId($planId);

        if (! $entity) {
            Log::warning('Recurring cycle succeeded: no matching entity', compact('planId'));
            return;
        }

        // Idempotency guard
        $eventId = "{$cycleId}_SUCCEEDED";
        if (Payment::where('xendit_event_id', $eventId)->exists()) {
            Log::info('Recurring cycle succeeded: already processed', compact('eventId'));
            return;
        }

        $sideEffects = null;

        try {
            DB::transaction(function () use ($entity, $payload, $eventId, &$sideEffects) {
                $this->extendExpiry($entity);

                $payment = $this->createPaymentRecord($entity, $payload, $eventId);

                $sideEffects = $this->recordAffiliateCommission($entity, $payment, $payload);

                $this->onPaymentSucceeded($entity, $payload);
            });

            $this->afterCommit($entity, $payload, $sideEffects);
        } catch (\Throwable $e) {
            Log::error('Recurring cycle succeeded handler failed', [
                'handler'  => static::class,
                'plan_id'  => $planId,
                'cycle_id' => $cycleId,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * TEMPLATE METHOD — handles plan activation (card linked, first charge).
     */
    final public function handlePlanActivated(array $payload): void
    {
        $planId = $payload['id'] ?? null;
        $entity = $this->findEntityByPlanId($planId);

        if (! $entity) {
            return;
        }

        // If the entity is already active (existing subscriber enabling auto-renew),
        // only update recurring_status — don't overwrite status or expires_at.
        $isAlreadyActive = $entity->status === $this->activeStatus()
            && $entity->expires_at?->isFuture();

        if ($isAlreadyActive) {
            $entity->update(['recurring_status' => 'ACTIVE']);
        } else {
            $entity->update([
                'recurring_status' => 'ACTIVE',
                'status'           => $this->activeStatus(),
                'expires_at'       => now()->addMonth(),
            ]);
        }

        Log::info('Recurring plan activated', ['handler' => static::class, 'plan_id' => $planId]);

        $this->onPlanActivated($entity, $payload);
    }

    /**
     * TEMPLATE METHOD — handles plan deactivation (cancelled or failed).
     * Does NOT cancel access — lets it expire naturally at expires_at.
     */
    final public function handlePlanInactivated(array $payload): void
    {
        $planId = $payload['id'] ?? null;
        $entity = $this->findEntityByPlanId($planId);

        if (! $entity) {
            return;
        }

        $entity->update(['recurring_status' => 'INACTIVE']);

        Log::info('Recurring plan inactivated', ['handler' => static::class, 'plan_id' => $planId]);
    }

    // ===================================================================
    // ABSTRACT — subclasses MUST implement
    // ===================================================================

    /** Find the local entity (Subscription, CreatorSubscription, etc.) by its Xendit plan ID. */
    abstract protected function findEntityByPlanId(string $planId): ?Model;

    /** The "active" status constant for this entity type. */
    abstract protected function activeStatus(): string;

    // ===================================================================
    // CONCRETE — shared logic, same for all entities
    // ===================================================================

    /** Extend the entity's expiry by 1 month. */
    protected function extendExpiry(Model $entity): void
    {
        $entity->update([
            'expires_at' => $entity->expires_at?->isFuture()
                ? $entity->expires_at->addMonth()
                : now()->addMonth(),
        ]);
    }

    /** Create a Payment record for the cycle. */
    protected function createPaymentRecord(Model $entity, array $payload, string $eventId): Payment
    {
        $gross         = (float) ($payload['amount'] ?? 0);
        $channel       = $payload['payment_method']['type'] ?? 'CREDIT_CARD';
        $processingFee = XenditService::collectionFee($channel, $gross);
        $platformFee   = $this->calculatePlatformFee($entity, $gross);

        return Payment::create([
            'subscription_id'    => $this->subscriptionId($entity),
            'community_id'       => $this->communityId($entity),
            'user_id'            => $entity->user_id,
            'amount'             => $gross,
            'processing_fee'     => $processingFee,
            'platform_fee'       => $platformFee,
            'currency'           => $payload['currency'] ?? 'PHP',
            'status'             => Payment::STATUS_PAID,
            'provider_reference' => $payload['id'] ?? null,
            'xendit_event_id'    => $eventId,
            'metadata'           => $payload,
            'paid_at'            => now(),
        ]);
    }

    // ===================================================================
    // HOOK METHODS — default no-op, override as needed
    // ===================================================================

    /** Record affiliate commission. Return cha-ching data array or null. */
    protected function recordAffiliateCommission(Model $entity, Payment $payment, array $payload): ?array
    {
        return null;
    }

    /** Entity-specific logic after payment succeeds (inside transaction). */
    protected function onPaymentSucceeded(Model $entity, array $payload): void {}

    /** Entity-specific logic after plan activates. */
    protected function onPlanActivated(Model $entity, array $payload): void {}

    /** Post-commit side effects: emails, events, etc. */
    protected function afterCommit(Model $entity, array $payload, ?array $sideEffects): void {}

    /** Calculate platform fee for this entity. Default: 0. */
    protected function calculatePlatformFee(Model $entity, float $gross): float
    {
        return 0;
    }

    /** Return the subscription ID for the Payment record. Default: null. */
    protected function subscriptionId(Model $entity): ?int
    {
        return null;
    }

    /** Return the community ID for the Payment record. Default: null. */
    protected function communityId(Model $entity): ?int
    {
        return null;
    }
}
