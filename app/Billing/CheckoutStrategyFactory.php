<?php

namespace App\Billing;

use App\Billing\Strategies\InvoiceCheckoutStrategy;
use App\Billing\Strategies\RecurringCheckoutStrategy;
use App\Contracts\CheckoutStrategy;

class CheckoutStrategyFactory
{
    /**
     * Resolve the checkout strategy.
     *
     * Currently all checkouts use the invoice strategy. Members opt into
     * recurring via the "Enable Auto-Renew" button after their first payment.
     * Set $forceRecurring = true when you're ready to enable recurring at checkout.
     */
    public static function make(?string $billingType, bool $forceRecurring = false): CheckoutStrategy
    {
        if ($forceRecurring && $billingType === 'monthly') {
            return app(RecurringCheckoutStrategy::class);
        }

        return app(InvoiceCheckoutStrategy::class);
    }
}
