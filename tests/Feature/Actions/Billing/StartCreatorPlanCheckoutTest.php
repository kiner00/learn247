<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\StartCreatorPlanCheckout;
use App\Models\CreatorSubscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StartCreatorPlanCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.xendit.secret_key'     => 'test_key',
            'services.xendit.callback_token'  => 'cb_token',
        ]);
    }

    public function test_invalid_plan_throws_validation_exception(): void
    {
        $user = User::factory()->create();

        $action = app(StartCreatorPlanCheckout::class);

        $this->expectException(ValidationException::class);
        $action->execute($user, 'invalid_plan');
    }

    public function test_already_subscribed_to_same_plan_throws_validation_exception(): void
    {
        $user = User::factory()->create();

        // Create active basic subscription
        CreatorSubscription::create([
            'user_id'    => $user->id,
            'plan'       => CreatorSubscription::PLAN_BASIC,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $action = app(StartCreatorPlanCheckout::class);

        $this->expectException(ValidationException::class);
        $action->execute($user, CreatorSubscription::PLAN_BASIC);
    }

    public function test_basic_plan_checkout_creates_subscription_and_returns_url(): void
    {
        Http::fake([
            // Recurring strategy calls createCustomer then createRecurringPlan
            'https://api.xendit.co/customers' => Http::response([
                'id' => 'cust_test_123',
            ], 200),
            'https://api.xendit.co/recurring/plans' => Http::response([
                'id'      => 'repl_basic_123',
                'status'  => 'REQUIRES_ACTION',
                'actions' => [
                    ['url' => 'https://linking.xendit.co/basic_123', 'action' => 'AUTH'],
                ],
            ], 200),
        ]);

        $user   = User::factory()->create();
        $action = app(StartCreatorPlanCheckout::class);

        $result = $action->execute($user, CreatorSubscription::PLAN_BASIC);

        $this->assertArrayHasKey('creator_subscription', $result);
        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertEquals('https://linking.xendit.co/basic_123', $result['checkout_url']);

        $this->assertDatabaseHas('creator_subscriptions', [
            'user_id'          => $user->id,
            'plan'             => CreatorSubscription::PLAN_BASIC,
            'status'           => CreatorSubscription::STATUS_PENDING,
            'xendit_plan_id'   => 'repl_basic_123',
            'recurring_status' => 'REQUIRES_ACTION',
        ]);
    }

    public function test_pro_plan_checkout_creates_subscription(): void
    {
        Http::fake([
            'https://api.xendit.co/customers' => Http::response([
                'id' => 'cust_test_456',
            ], 200),
            'https://api.xendit.co/recurring/plans' => Http::response([
                'id'      => 'repl_pro_123',
                'status'  => 'REQUIRES_ACTION',
                'actions' => [
                    ['url' => 'https://linking.xendit.co/pro_123', 'action' => 'AUTH'],
                ],
            ], 200),
        ]);

        $user   = User::factory()->create();
        $action = app(StartCreatorPlanCheckout::class);

        $result = $action->execute($user, CreatorSubscription::PLAN_PRO);

        $this->assertDatabaseHas('creator_subscriptions', [
            'user_id'        => $user->id,
            'plan'           => CreatorSubscription::PLAN_PRO,
            'status'         => CreatorSubscription::STATUS_PENDING,
            'xendit_plan_id' => 'repl_pro_123',
        ]);
    }

    public function test_xendit_failure_propagates_exception(): void
    {
        Http::fake([
            'https://api.xendit.co/customers' => Http::response([
                'id' => 'cust_test_789',
            ], 200),
            'https://api.xendit.co/recurring/plans' => Http::response(['error' => 'bad'], 500),
        ]);

        $user   = User::factory()->create();
        $action = app(StartCreatorPlanCheckout::class);

        $this->expectException(\RuntimeException::class);
        $action->execute($user, CreatorSubscription::PLAN_BASIC);
    }
}
