<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CreatorSubscription;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecurringCancellationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('deactivateRecurringPlan')->andReturn(['status' => 'INACTIVE']);
        $this->app->instance(XenditService::class, $xendit);
    }

    public function test_cancel_subscription_requires_auth(): void
    {
        $subscription = Subscription::factory()->create(['xendit_plan_id' => 'repl_auth']);

        $this->post("/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertRedirect('/login');
    }

    public function test_cancel_subscription_requires_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id'     => $community->id,
            'user_id'          => $otherUser->id,
            'status'           => Subscription::STATUS_ACTIVE,
            'xendit_plan_id'   => 'repl_owned',
            'recurring_status' => 'ACTIVE',
            'expires_at'       => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertForbidden();
    }

    public function test_cancel_subscription_requires_recurring(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'xendit_id'    => 'inv_not_recurring',
            'expires_at'   => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertStatus(400);
    }

    public function test_cancel_subscription_succeeds(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id'     => $community->id,
            'user_id'          => $user->id,
            'status'           => Subscription::STATUS_ACTIVE,
            'xendit_plan_id'   => 'repl_cancel_ok',
            'recurring_status' => 'ACTIVE',
            'expires_at'       => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertRedirect()
            ->assertSessionHas('success');

        $subscription->refresh();
        $this->assertEquals('INACTIVE', $subscription->recurring_status);
    }

    public function test_cancel_creator_plan_succeeds(): void
    {
        $user = User::factory()->create();
        $creatorSub = CreatorSubscription::create([
            'user_id'          => $user->id,
            'plan'             => CreatorSubscription::PLAN_BASIC,
            'status'           => CreatorSubscription::STATUS_ACTIVE,
            'xendit_plan_id'   => 'repl_creator_cancel_ctrl',
            'recurring_status' => 'ACTIVE',
            'expires_at'       => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post('/creator/plan/cancel-recurring')
            ->assertRedirect()
            ->assertSessionHas('success');

        $creatorSub->refresh();
        $this->assertEquals('INACTIVE', $creatorSub->recurring_status);
    }

    public function test_cancel_creator_plan_fails_without_active_recurring(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/creator/plan/cancel-recurring')
            ->assertStatus(404);
    }
}
