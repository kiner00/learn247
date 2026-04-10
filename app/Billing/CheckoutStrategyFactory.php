<?php

namespace App\Billing;

use App\Billing\Strategies\InvoiceCheckoutStrategy;
use App\Billing\Strategies\RecurringCheckoutStrategy;
use App\Contracts\CheckoutStrategy;

class CheckoutStrategyFactory
{
    public static function make(?string $billingType): CheckoutStrategy
    {
        return match ($billingType) {
            'monthly' => app(RecurringCheckoutStrategy::class),
            default   => app(InvoiceCheckoutStrategy::class),
        };
    }
}
