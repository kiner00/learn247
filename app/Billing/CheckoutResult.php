<?php

namespace App\Billing;

class CheckoutResult
{
    public function __construct(
        public readonly string $checkoutUrl,
        public readonly ?string $invoiceId = null,
        public readonly ?string $invoiceUrl = null,
        public readonly ?string $planId = null,
        public readonly ?string $customerId = null,
        public readonly ?string $recurringStatus = null,
    ) {}
}
