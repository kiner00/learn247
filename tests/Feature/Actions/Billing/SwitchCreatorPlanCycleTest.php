<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\SwitchCreatorPlanCycle;
use App\Models\CreatorSubscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class SwitchCreatorPlanCycleTest extends TestCase
{
    use RefreshDatabase;

    private function activeSub(User $user, array $overrides = []): CreatorSubscription
    {
        return CreatorSubscription::create(array_merge([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_BASIC,
            'billing_cycle' => CreatorSubscription::CYCLE_MONTHLY,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addDays(15),
            'xendit_id' => 'inv_switch_'.uniqid(),
        ], $overrides));
    }

    public function test_invalid_cycle_throws_validation(): void
    {
        $user = User::factory()->create();
        $sub = $this->activeSub($user);

        $this->expectException(ValidationException::class);
        app(SwitchCreatorPlanCycle::class)->execute($sub, 'forever');
    }

    public function test_same_cycle_throws_validation(): void
    {
        $user = User::factory()->create();
        $sub = $this->activeSub($user, ['billing_cycle' => CreatorSubscription::CYCLE_MONTHLY]);

        $this->expectException(ValidationException::class);
        app(SwitchCreatorPlanCycle::class)->execute($sub, CreatorSubscription::CYCLE_MONTHLY);
    }

    public function test_inactive_subscription_cannot_switch(): void
    {
        $user = User::factory()->create();
        $sub = $this->activeSub($user, ['status' => CreatorSubscription::STATUS_EXPIRED]);

        $this->expectException(ValidationException::class);
        app(SwitchCreatorPlanCycle::class)->execute($sub, CreatorSubscription::CYCLE_ANNUAL);
    }

    public function test_switch_updates_cycle_when_no_recurring_plan(): void
    {
        $user = User::factory()->create();
        $sub = $this->activeSub($user); // No xendit_plan_id

        $result = app(SwitchCreatorPlanCycle::class)->execute($sub, CreatorSubscription::CYCLE_ANNUAL);

        $this->assertEquals(CreatorSubscription::CYCLE_ANNUAL, $result['creator_subscription']->billing_cycle);
        $this->assertNull($result['linking_url']);
        $this->assertDatabaseHas('creator_subscriptions', [
            'id' => $sub->id,
            'billing_cycle' => CreatorSubscription::CYCLE_ANNUAL,
        ]);
    }

    public function test_switch_swaps_recurring_plan_when_active(): void
    {
        $user = User::factory()->create(['xendit_customer_id' => 'cust_123']);
        $sub = $this->activeSub($user, [
            'plan' => CreatorSubscription::PLAN_PRO,
            'billing_cycle' => CreatorSubscription::CYCLE_MONTHLY,
            'xendit_plan_id' => 'xplan_old',
            'xendit_customer_id' => 'cust_123',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(10),
        ]);

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('deactivateRecurringPlan')
            ->once()
            ->with('xplan_old');
        $xendit->shouldReceive('createRecurringPlan')
            ->once()
            ->andReturn([
                'id' => 'xplan_new',
                'status' => 'REQUIRES_ACTION',
                'actions' => [['url' => 'https://xendit.co/link/xyz']],
            ]);

        $action = new SwitchCreatorPlanCycle($xendit);
        $result = $action->execute($sub, CreatorSubscription::CYCLE_ANNUAL);

        $this->assertEquals('https://xendit.co/link/xyz', $result['linking_url']);
        $this->assertDatabaseHas('creator_subscriptions', [
            'id' => $sub->id,
            'billing_cycle' => CreatorSubscription::CYCLE_ANNUAL,
            'xendit_plan_id' => 'xplan_new',
            'recurring_status' => 'REQUIRES_ACTION',
        ]);
    }
}
