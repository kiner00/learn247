<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Billing\CheckoutStrategyFactory;
use App\Billing\Strategies\InvoiceCheckoutStrategy;
use App\Billing\Strategies\RecurringCheckoutStrategy;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MonthlySubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_community_uses_invoice_by_default(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'monthly']);

        $xendit = Mockery::mock(XenditService::class);

        // Default behavior: invoice strategy even for monthly (recurring is opt-in via Enable Auto-Renew)
        $xendit->shouldReceive('createInvoice')
            ->once()
            ->andReturn([
                'id'          => 'inv_monthly_default',
                'invoice_url' => 'https://checkout.xendit.co/monthly_default',
            ]);

        $xendit->shouldNotReceive('createCustomer');
        $xendit->shouldNotReceive('createRecurringPlan');

        $this->app->instance(XenditService::class, $xendit);
        $action = app(StartSubscriptionCheckout::class);

        $result = $action->execute($user, $community);

        $this->assertEquals('https://checkout.xendit.co/monthly_default', $result['checkout_url']);

        $this->assertDatabaseHas('subscriptions', [
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'status'         => Subscription::STATUS_PENDING,
            'xendit_id'      => 'inv_monthly_default',
            'xendit_plan_id' => null,
        ]);
    }

    public function test_factory_returns_recurring_when_forced(): void
    {
        $strategy = CheckoutStrategyFactory::make('monthly', forceRecurring: true);
        $this->assertInstanceOf(RecurringCheckoutStrategy::class, $strategy);
    }

    public function test_factory_returns_invoice_for_monthly_without_force(): void
    {
        $strategy = CheckoutStrategyFactory::make('monthly');
        $this->assertInstanceOf(InvoiceCheckoutStrategy::class, $strategy);
    }

    public function test_one_time_community_uses_invoice_strategy(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);

        $xendit = Mockery::mock(XenditService::class);

        $xendit->shouldReceive('createInvoice')
            ->once()
            ->andReturn([
                'id'          => 'inv_onetime_001',
                'invoice_url' => 'https://checkout.xendit.co/onetime',
            ]);

        $xendit->shouldNotReceive('createCustomer');
        $xendit->shouldNotReceive('createRecurringPlan');

        $this->app->instance(XenditService::class, $xendit);
        $action = app(StartSubscriptionCheckout::class);

        $result = $action->execute($user, $community);

        $this->assertEquals('https://checkout.xendit.co/onetime', $result['checkout_url']);

        $this->assertDatabaseHas('subscriptions', [
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'status'         => Subscription::STATUS_PENDING,
            'xendit_id'      => 'inv_onetime_001',
            'xendit_plan_id' => null,
        ]);
    }
}
