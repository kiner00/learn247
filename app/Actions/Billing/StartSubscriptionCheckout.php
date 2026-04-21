<?php

namespace App\Actions\Billing;

use App\Billing\CheckoutContext;
use App\Billing\CheckoutStrategyFactory;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StartSubscriptionCheckout
{
    /**
     * @return array{subscription: Subscription, checkout_url: string}
     *
     * @throws ValidationException|\RuntimeException
     */
    public function execute(User $user, Community $community, ?string $affiliateCode = null, ?string $successRedirectUrl = null): array
    {
        if (! $community->isAcceptingNewMembers()) {
            throw ValidationException::withMessages([
                'community' => 'This community is no longer accepting new members.',
            ]);
        }

        if ($community->isFree()) {
            throw ValidationException::withMessages([
                'community' => 'This community is free. No checkout required.',
            ]);
        }

        $existing = Subscription::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'subscription' => 'You already have an active subscription.',
            ]);
        }

        try {
            // Strategy decides how to charge: invoice (one-time) or recurring plan (monthly)
            $strategy = CheckoutStrategyFactory::make($community->billing_type);

            $description = $community->hasPromoFirstMonth()
                ? "First month: {$community->name}"
                : "Subscription to {$community->name}";

            $result = $strategy->initiatePayment(new CheckoutContext(
                user: $user,
                amount: $community->firstChargeAmount(),
                currency: $community->currency,
                description: $description,
                referenceId: "{$community->slug}_sub_{$user->id}_".time(),
                successUrl: $successRedirectUrl ?? config('app.url')."/communities/{$community->slug}",
                failureUrl: config('app.url')."/communities/{$community->slug}",
                itemName: "Community: {$community->name}",
                itemCategory: 'Community Subscription',
            ));

            // Resolve affiliate from cookie code (must be active and for this community)
            $affiliateId = null;
            if ($affiliateCode) {
                $affiliate = Affiliate::where('code', $affiliateCode)
                    ->where('community_id', $community->id)
                    ->where('status', Affiliate::STATUS_ACTIVE)
                    ->first();
                $affiliateId = $affiliate?->id;
            }

            $subscription = Subscription::create([
                'community_id' => $community->id,
                'user_id' => $user->id,
                'affiliate_id' => $affiliateId,
                'status' => Subscription::STATUS_PENDING,
                'xendit_id' => $result->invoiceId,
                'xendit_invoice_url' => $result->invoiceUrl,
                'xendit_plan_id' => $result->planId,
                'xendit_customer_id' => $result->customerId,
                'recurring_status' => $result->recurringStatus,
            ]);

            return ['subscription' => $subscription, 'checkout_url' => $result->checkoutUrl];
        } catch (\Throwable $e) {
            Log::error('StartSubscriptionCheckout failed', [
                'user_id' => $user->id,
                'community_id' => $community->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
