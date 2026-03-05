<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use App\Services\XenditService;

class CreateRenewalInvoice
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * Create a renewal Xendit invoice for an active subscription and update
     * the subscription's xendit_id so the webhook can resolve it on payment.
     *
     * @return string The Xendit invoice URL to send to the user
     * @throws \RuntimeException
     */
    public function execute(Subscription $subscription): string
    {
        $user      = $subscription->user;
        $community = $subscription->community;

        $invoice = $this->xendit->createInvoice([
            'external_id' => "renew_{$community->id}_{$user->id}_" . time(),
            'amount'      => (float) $community->price,
            'currency'    => $community->currency,
            'description' => "Renewal: {$community->name}",
            'customer'    => ['given_names' => $user->name, 'email' => $user->email],
            'customer_notification_preference' => [
                'invoice_created' => ['email'],
                'invoice_paid'    => ['email'],
            ],
            'success_redirect_url' => config('app.url') . "/communities/{$community->slug}",
            'failure_redirect_url' => config('app.url') . "/communities/{$community->slug}",
            'items' => [[
                'name'     => "Community Renewal: {$community->name}",
                'quantity' => 1,
                'price'    => (float) $community->price,
                'category' => 'Community Subscription',
            ]],
        ]);

        // Update xendit_id so the webhook handler can resolve this subscription when paid
        $subscription->update([
            'xendit_id'          => $invoice['id'],
            'xendit_invoice_url' => $invoice['invoice_url'],
        ]);

        return $invoice['invoice_url'];
    }
}
