<?php

namespace App\Actions\Billing;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use App\Support\InvoiceBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StartSubscriptionCheckout
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * @return array{subscription: Subscription, checkout_url: string}
     * @throws ValidationException|\RuntimeException
     */
    public function execute(User $user, Community $community, ?string $affiliateCode = null, ?string $successRedirectUrl = null): array
    {
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
            $externalId = "{$community->slug}_sub_{$user->id}_" . time();

            $invoice = $this->xendit->createInvoice(
                InvoiceBuilder::make()
                    ->externalId($externalId)
                    ->amount((float) $community->price)
                    ->currency($community->currency)
                    ->description("Subscription to {$community->name}")
                    ->customer($user)
                    ->successUrl($successRedirectUrl ?? config('app.url') . "/communities/{$community->slug}")
                    ->failureUrl(config('app.url') . "/communities/{$community->slug}")
                    ->item("Community: {$community->name}", (float) $community->price, 'Community Subscription')
                    ->toArray()
            );

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
                'community_id'       => $community->id,
                'user_id'            => $user->id,
                'affiliate_id'       => $affiliateId,
                'status'             => Subscription::STATUS_PENDING,
                'xendit_id'          => $invoice['id'],
                'xendit_invoice_url' => $invoice['invoice_url'],
            ]);

            return ['subscription' => $subscription, 'checkout_url' => $invoice['invoice_url']];
        } catch (\Throwable $e) {
            Log::error('StartSubscriptionCheckout failed', [
                'user_id'      => $user->id,
                'community_id' => $community->id,
                'error'        => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
