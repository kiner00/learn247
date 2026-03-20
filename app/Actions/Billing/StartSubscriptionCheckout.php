<?php

namespace App\Actions\Billing;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
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

        $externalId = "{$community->slug}_sub_{$user->id}_" . time();

        $invoice = $this->xendit->createInvoice([
            'external_id' => $externalId,
            'amount'      => (float) $community->price,
            'currency'    => $community->currency,
            'description' => "Subscription to {$community->name}",
            'customer'    => ['given_names' => $user->name, 'email' => $user->email],
            'customer_notification_preference' => [
                'invoice_created' => ['email'],
                'invoice_paid'    => ['email'],
            ],
            'success_redirect_url' => $successRedirectUrl ?? config('app.url') . "/communities/{$community->slug}",
            'failure_redirect_url' => config('app.url') . "/communities/{$community->slug}",
            'items' => [[
                'name'     => "Community: {$community->name}",
                'quantity' => 1,
                'price'    => (float) $community->price,
                'category' => 'Community Subscription',
            ]],
        ]);

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
    }
}
