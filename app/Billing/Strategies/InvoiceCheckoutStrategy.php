<?php

namespace App\Billing\Strategies;

use App\Billing\CheckoutContext;
use App\Billing\CheckoutResult;
use App\Contracts\CheckoutStrategy;
use App\Services\XenditService;
use App\Support\InvoiceBuilder;

class InvoiceCheckoutStrategy implements CheckoutStrategy
{
    public function __construct(private readonly XenditService $xendit) {}

    public function initiatePayment(CheckoutContext $context): CheckoutResult
    {
        $invoice = $this->xendit->createInvoice(
            InvoiceBuilder::make()
                ->externalId($context->referenceId)
                ->amount($context->amount)
                ->currency($context->currency)
                ->description($context->description)
                ->customer($context->user)
                ->successUrl($context->successUrl)
                ->failureUrl($context->failureUrl)
                ->item($context->itemName, $context->amount, $context->itemCategory)
                ->toArray()
        );

        return new CheckoutResult(
            checkoutUrl: $invoice['invoice_url'],
            invoiceId: $invoice['id'],
            invoiceUrl: $invoice['invoice_url'],
        );
    }
}
