<?php

namespace App\Actions\Billing;

use App\Billing\CheckoutContext;
use App\Billing\CheckoutStrategyFactory;
use App\Models\Affiliate;
use App\Models\Curzzo;
use App\Models\CurzzoPurchase;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StartCurzzoCheckout
{
    /**
     * @return array{purchase: CurzzoPurchase, checkout_url: string}
     */
    public function execute(User $user, Curzzo $curzzo, ?string $affiliateCode = null, ?string $successRedirectUrl = null): array
    {
        // Only bot-level purchases go through Curzzo checkout. 'inclusive' bots are
        // unlocked via the community's paid membership, and 'free'/'member_once' never bill.
        // Guarding here prevents stale `price` on a repurposed bot from creating an invoice.
        if (! in_array($curzzo->access_type, ['paid_once', 'paid_monthly'], true)) {
            throw ValidationException::withMessages([
                'curzzo' => 'This Curzzo is not available for individual purchase.',
            ]);
        }

        if ($curzzo->isFree()) {
            throw ValidationException::withMessages([
                'curzzo' => 'This Curzzo is free. No checkout required.',
            ]);
        }

        $existing = CurzzoPurchase::where('curzzo_id', $curzzo->id)
            ->where('user_id', $user->id)
            ->where('status', CurzzoPurchase::STATUS_PAID)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'curzzo' => 'You already have access to this Curzzo.',
            ]);
        }

        try {
            $community = $curzzo->community;

            // Strategy decides how to charge: invoice (one-time) or recurring plan (monthly)
            $strategy = CheckoutStrategyFactory::make($curzzo->billing_type ?? 'one_time');

            $result = $strategy->initiatePayment(new CheckoutContext(
                user: $user,
                amount: (float) $curzzo->price,
                currency: $curzzo->currency ?? $community->currency ?? 'PHP',
                description: "Curzzo: {$curzzo->name} — {$community->name}",
                referenceId: "curzzo_{$curzzo->id}_user_{$user->id}_".time(),
                successUrl: $successRedirectUrl ?? config('app.url')."/communities/{$community->slug}/curzzos",
                failureUrl: config('app.url')."/communities/{$community->slug}/curzzos",
                itemName: "Curzzo: {$curzzo->name}",
                itemCategory: 'AI Bot',
            ));

            $affiliateId = null;
            if ($affiliateCode) {
                $affiliate = Affiliate::where('code', $affiliateCode)
                    ->where('community_id', $community->id)
                    ->where('status', Affiliate::STATUS_ACTIVE)
                    ->first();
                $affiliateId = $affiliate?->id;
            }

            $purchase = CurzzoPurchase::create([
                'user_id' => $user->id,
                'curzzo_id' => $curzzo->id,
                'affiliate_id' => $affiliateId,
                'status' => CurzzoPurchase::STATUS_PENDING,
                'xendit_id' => $result->invoiceId,
                'xendit_plan_id' => $result->planId,
                'xendit_customer_id' => $result->customerId,
                'recurring_status' => $result->recurringStatus,
            ]);

            return ['purchase' => $purchase, 'checkout_url' => $result->checkoutUrl];
        } catch (\Throwable $e) {
            Log::error('StartCurzzoCheckout failed', [
                'user_id' => $user->id,
                'curzzo_id' => $curzzo->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
