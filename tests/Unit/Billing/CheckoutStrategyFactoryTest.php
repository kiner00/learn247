<?php

namespace Tests\Unit\Billing;

use App\Billing\CheckoutStrategyFactory;
use App\Billing\Strategies\InvoiceCheckoutStrategy;
use App\Billing\Strategies\RecurringCheckoutStrategy;
use Tests\TestCase;

class CheckoutStrategyFactoryTest extends TestCase
{
    public function test_returns_recurring_strategy_for_monthly(): void
    {
        $strategy = CheckoutStrategyFactory::make('monthly');
        $this->assertInstanceOf(RecurringCheckoutStrategy::class, $strategy);
    }

    public function test_returns_invoice_strategy_for_one_time(): void
    {
        $strategy = CheckoutStrategyFactory::make('one_time');
        $this->assertInstanceOf(InvoiceCheckoutStrategy::class, $strategy);
    }

    public function test_returns_invoice_strategy_for_null(): void
    {
        $strategy = CheckoutStrategyFactory::make(null);
        $this->assertInstanceOf(InvoiceCheckoutStrategy::class, $strategy);
    }

    public function test_returns_invoice_strategy_for_unknown_type(): void
    {
        $strategy = CheckoutStrategyFactory::make('unknown');
        $this->assertInstanceOf(InvoiceCheckoutStrategy::class, $strategy);
    }
}
