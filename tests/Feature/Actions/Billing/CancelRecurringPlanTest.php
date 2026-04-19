<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\CancelRecurringPlan;
use App\Models\Community;
use App\Models\CreatorSubscription;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CancelRecurringPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivates_recurring_plan_and_sets_inactive(): void
    {
        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('deactivateRecurringPlan')
            ->once()
            ->with('repl_cancel_001')
            ->andReturn(['id' => 'repl_cancel_001', 'status' => 'INACTIVE']);

        $this->app->instance(XenditService::class, $xendit);

        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_cancel_001',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(20),
        ]);

        $action = app(CancelRecurringPlan::class);
        $action->execute($subscription);

        $subscription->refresh();
        $this->assertEquals('INACTIVE', $subscription->recurring_status);
        // Status should remain active — access continues
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
        // Expiry should remain unchanged
        $this->assertNotNull($subscription->expires_at);
    }

    public function test_does_nothing_if_already_inactive(): void
    {
        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldNotReceive('deactivateRecurringPlan');

        $this->app->instance(XenditService::class, $xendit);

        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_already_inactive',
            'recurring_status' => 'INACTIVE',
            'expires_at' => now()->addDays(10),
        ]);

        $action = app(CancelRecurringPlan::class);
        $action->execute($subscription);

        // No exception, no API call
        $this->assertTrue(true);
    }

    public function test_throws_if_not_a_recurring_subscription(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_not_recurring',
            'expires_at' => now()->addDays(20),
        ]);

        $action = app(CancelRecurringPlan::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not on a recurring plan');
        $action->execute($subscription);
    }

    public function test_works_with_creator_subscription(): void
    {
        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('deactivateRecurringPlan')
            ->once()
            ->with('repl_creator_cancel')
            ->andReturn(['id' => 'repl_creator_cancel', 'status' => 'INACTIVE']);

        $this->app->instance(XenditService::class, $xendit);

        $user = User::factory()->create();
        $creatorSub = CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_PRO,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_creator_cancel',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(20),
        ]);

        $action = app(CancelRecurringPlan::class);
        $action->execute($creatorSub);

        $creatorSub->refresh();
        $this->assertEquals('INACTIVE', $creatorSub->recurring_status);
        $this->assertEquals(CreatorSubscription::STATUS_ACTIVE, $creatorSub->status);
    }

    public function test_rethrows_xendit_api_failure(): void
    {
        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('deactivateRecurringPlan')
            ->once()
            ->andThrow(new \RuntimeException('Xendit API error'));

        $this->app->instance(XenditService::class, $xendit);

        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_fail_001',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(20),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Xendit API error');

        $action = app(CancelRecurringPlan::class);
        $action->execute($subscription);
    }

    public function test_recurring_status_not_updated_on_api_failure(): void
    {
        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('deactivateRecurringPlan')
            ->once()
            ->andThrow(new \RuntimeException('Network error'));

        $this->app->instance(XenditService::class, $xendit);

        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_no_update',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(20),
        ]);

        try {
            $action = app(CancelRecurringPlan::class);
            $action->execute($subscription);
        } catch (\RuntimeException) {
            // expected
        }

        $subscription->refresh();
        $this->assertEquals('ACTIVE', $subscription->recurring_status);
    }
}
