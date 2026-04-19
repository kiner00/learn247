<?php

namespace Tests\Feature\Actions\Billing;

use App\Billing\CheckoutContext;
use App\Billing\Strategies\InvoiceCheckoutStrategy;
use App\Billing\Strategies\RecurringCheckoutStrategy;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecurringCheckoutStrategyTest extends TestCase
{
    use RefreshDatabase;

    private function makeContext(User $user): CheckoutContext
    {
        return new CheckoutContext(
            user: $user,
            amount: 499.00,
            currency: 'PHP',
            description: 'Test subscription',
            referenceId: 'test_ref_'.time(),
            successUrl: 'https://example.com/success',
            failureUrl: 'https://example.com/failure',
            itemName: 'Test Item',
            itemCategory: 'Test',
        );
    }

    public function test_recurring_strategy_creates_customer_and_plan(): void
    {
        $user = User::factory()->create();
        $xendit = Mockery::mock(XenditService::class);

        $xendit->shouldReceive('createCustomer')
            ->once()
            ->andReturn(['id' => 'cust_test_001']);

        $xendit->shouldReceive('createRecurringPlan')
            ->once()
            ->andReturn([
                'id' => 'repl_test_001',
                'status' => 'REQUIRES_ACTION',
                'actions' => [
                    ['url' => 'https://linking.xendit.co/test', 'action' => 'AUTH'],
                ],
            ]);

        $this->app->instance(XenditService::class, $xendit);
        $strategy = app(RecurringCheckoutStrategy::class);
        $result = $strategy->initiatePayment($this->makeContext($user));

        $this->assertEquals('https://linking.xendit.co/test', $result->checkoutUrl);
        $this->assertEquals('repl_test_001', $result->planId);
        $this->assertEquals('cust_test_001', $result->customerId);
        $this->assertEquals('REQUIRES_ACTION', $result->recurringStatus);
        $this->assertNull($result->invoiceId);
        $this->assertNull($result->invoiceUrl);
    }

    public function test_recurring_strategy_reuses_existing_customer(): void
    {
        $user = User::factory()->create(['xendit_customer_id' => 'cust_existing']);
        $xendit = Mockery::mock(XenditService::class);

        // Should NOT call createCustomer since user already has one
        $xendit->shouldNotReceive('createCustomer');

        $xendit->shouldReceive('createRecurringPlan')
            ->once()
            ->andReturn([
                'id' => 'repl_reuse',
                'status' => 'REQUIRES_ACTION',
                'actions' => [
                    ['url' => 'https://linking.xendit.co/reuse', 'action' => 'AUTH'],
                ],
            ]);

        $this->app->instance(XenditService::class, $xendit);
        $strategy = app(RecurringCheckoutStrategy::class);
        $result = $strategy->initiatePayment($this->makeContext($user));

        $this->assertEquals('cust_existing', $result->customerId);
    }

    public function test_recurring_strategy_saves_customer_id_to_user(): void
    {
        $user = User::factory()->create();
        $xendit = Mockery::mock(XenditService::class);

        $xendit->shouldReceive('createCustomer')
            ->once()
            ->andReturn(['id' => 'cust_new_saved']);

        $xendit->shouldReceive('createRecurringPlan')
            ->once()
            ->andReturn([
                'id' => 'repl_save',
                'status' => 'REQUIRES_ACTION',
                'actions' => [['url' => 'https://linking.xendit.co/save', 'action' => 'AUTH']],
            ]);

        $this->app->instance(XenditService::class, $xendit);
        $strategy = app(RecurringCheckoutStrategy::class);
        $strategy->initiatePayment($this->makeContext($user));

        $user->refresh();
        $this->assertEquals('cust_new_saved', $user->xendit_customer_id);
    }

    public function test_invoice_strategy_creates_invoice(): void
    {
        $user = User::factory()->create();
        $xendit = Mockery::mock(XenditService::class);

        $xendit->shouldReceive('createInvoice')
            ->once()
            ->andReturn([
                'id' => 'inv_strat_001',
                'invoice_url' => 'https://checkout.xendit.co/strat',
            ]);

        $this->app->instance(XenditService::class, $xendit);
        $strategy = app(InvoiceCheckoutStrategy::class);
        $result = $strategy->initiatePayment($this->makeContext($user));

        $this->assertEquals('https://checkout.xendit.co/strat', $result->checkoutUrl);
        $this->assertEquals('inv_strat_001', $result->invoiceId);
        $this->assertEquals('https://checkout.xendit.co/strat', $result->invoiceUrl);
        $this->assertNull($result->planId);
        $this->assertNull($result->customerId);
        $this->assertNull($result->recurringStatus);
    }
}
