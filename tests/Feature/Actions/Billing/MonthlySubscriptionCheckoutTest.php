<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\StartSubscriptionCheckout;
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

    public function test_monthly_community_uses_recurring_strategy(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'monthly']);

        $xendit = Mockery::mock(XenditService::class);

        // Should call createCustomer + createRecurringPlan (not createInvoice)
        $xendit->shouldReceive('createCustomer')
            ->once()
            ->andReturn(['id' => 'cust_monthly_001']);

        $xendit->shouldReceive('createRecurringPlan')
            ->once()
            ->andReturn([
                'id'      => 'repl_monthly_001',
                'status'  => 'REQUIRES_ACTION',
                'actions' => [
                    ['url' => 'https://linking.xendit.co/monthly', 'action' => 'AUTH'],
                ],
            ]);

        $xendit->shouldNotReceive('createInvoice');

        $this->app->instance(XenditService::class, $xendit);
        $action = app(StartSubscriptionCheckout::class);

        $result = $action->execute($user, $community);

        $this->assertEquals('https://linking.xendit.co/monthly', $result['checkout_url']);

        $this->assertDatabaseHas('subscriptions', [
            'community_id'     => $community->id,
            'user_id'          => $user->id,
            'status'           => Subscription::STATUS_PENDING,
            'xendit_plan_id'   => 'repl_monthly_001',
            'xendit_customer_id' => 'cust_monthly_001',
            'recurring_status' => 'REQUIRES_ACTION',
            'xendit_id'        => null,
        ]);
    }

    public function test_one_time_community_uses_invoice_strategy(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);

        $xendit = Mockery::mock(XenditService::class);

        // Should call createInvoice (not recurring methods)
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

    public function test_unknown_billing_type_defaults_to_invoice(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);

        $xendit = Mockery::mock(XenditService::class);

        $xendit->shouldReceive('createInvoice')
            ->once()
            ->andReturn([
                'id'          => 'inv_null_billing',
                'invoice_url' => 'https://checkout.xendit.co/null',
            ]);

        $xendit->shouldNotReceive('createCustomer');
        $xendit->shouldNotReceive('createRecurringPlan');

        $this->app->instance(XenditService::class, $xendit);
        $action = app(StartSubscriptionCheckout::class);

        $result = $action->execute($user, $community);

        $this->assertDatabaseHas('subscriptions', [
            'xendit_id'      => 'inv_null_billing',
            'xendit_plan_id' => null,
        ]);
    }
}
