<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use App\Services\XenditService;
use App\Support\InvoiceBuilder;
use Illuminate\Support\Facades\Log;

class CreateRenewalInvoice
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * Create a renewal Xendit invoice for an active subscription and update
     * the subscription's xendit_id so the webhook can resolve it on payment.
     *
     * @return string The Xendit invoice URL to send to the user
     *
     * @throws \RuntimeException
     */
    public function execute(Subscription $subscription): string
    {
        $user = $subscription->user;
        $community = $subscription->community;

        try {
            $invoice = $this->xendit->createInvoice(
                InvoiceBuilder::make()
                    ->externalId("{$community->slug}_renew_{$user->id}_".time())
                    ->amount((float) $community->price)
                    ->currency($community->currency)
                    ->description("Renewal: {$community->name}")
                    ->customer($user)
                    ->successUrl(config('app.url')."/communities/{$community->slug}")
                    ->failureUrl(config('app.url')."/communities/{$community->slug}")
                    ->item("Community Renewal: {$community->name}", (float) $community->price, 'Community Subscription')
                    ->toArray()
            );

            // Update xendit_id so the webhook handler can resolve this subscription when paid
            $subscription->update([
                'xendit_id' => $invoice['id'],
                'xendit_invoice_url' => $invoice['invoice_url'],
            ]);

            return $invoice['invoice_url'];
        } catch (\Throwable $e) {
            Log::error('CreateRenewalInvoice failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
