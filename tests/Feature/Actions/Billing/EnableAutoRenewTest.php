<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\EnableAutoRenew;
use App\Actions\Billing\HandleXenditWebhook;
use App\Models\Community;
use App\Models\CreatorSubscription;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class EnableAutoRenewTest extends TestCase
{
    use RefreshDatabase;

    private function mockXendit(): void
    {
        $xendit = Mockery::mock(XenditService::class);

        $xendit->shouldReceive('createCustomer')
            ->andReturn(['id' => 'cust_auto_001']);

        $xendit->shouldReceive('createRecurringPlan')
            ->andReturn([
                'id' => 'repl_auto_001',
                'status' => 'REQUIRES_ACTION',
                'actions' => [
                    ['url' => 'https://linking.xendit.co/auto', 'action' => 'AUTH'],
                ],
            ]);

        $this->app->instance(XenditService::class, $xendit);
    }

    public function test_creates_recurring_plan_for_existing_subscription(): void
    {
        $this->mockXendit();

        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_legacy_001',
            'expires_at' => now()->addDays(20),
        ]);

        $action = app(EnableAutoRenew::class);
        $linkingUrl = $action->executeForSubscription($subscription);

        $this->assertEquals('https://linking.xendit.co/auto', $linkingUrl);

        $subscription->refresh();
        $this->assertEquals('repl_auto_001', $subscription->xendit_plan_id);
        $this->assertEquals('cust_auto_001', $subscription->xendit_customer_id);
        $this->assertEquals('REQUIRES_ACTION', $subscription->recurring_status);
    }

    public function test_throws_if_already_recurring(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_existing',
            'expires_at' => now()->addDays(20),
        ]);

        $action = app(EnableAutoRenew::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already has auto-renew');
        $action->executeForSubscription($subscription);
    }

    public function test_creates_recurring_plan_for_creator_subscription(): void
    {
        $this->mockXendit();

        $user = User::factory()->create();
        $creatorSub = CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_BASIC,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_creator_legacy',
            'expires_at' => now()->addDays(15),
        ]);

        $action = app(EnableAutoRenew::class);
        $linkingUrl = $action->executeForCreatorPlan($creatorSub);

        $this->assertEquals('https://linking.xendit.co/auto', $linkingUrl);

        $creatorSub->refresh();
        $this->assertEquals('repl_auto_001', $creatorSub->xendit_plan_id);
        $this->assertEquals('REQUIRES_ACTION', $creatorSub->recurring_status);
    }

    public function test_plan_activated_preserves_expiry_for_existing_active_subscription(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $originalExpiry = now()->addDays(20);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_preserve_exp',
            'recurring_status' => 'REQUIRES_ACTION',
            'expires_at' => $originalExpiry,
        ]);

        $request = Request::create('/xendit/webhook', 'POST', [
            'event' => 'recurring.plan.activated',
            'data' => ['id' => 'repl_preserve_exp'],
        ]);
        $request->headers->set('x-callback-token', 'valid-token');

        app(HandleXenditWebhook::class)->execute($request);

        $subscription->refresh();
        $this->assertEquals('ACTIVE', $subscription->recurring_status);
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
        // Expiry should remain unchanged — subscriber already has active period
        $this->assertTrue(
            abs($subscription->expires_at->diffInSeconds($originalExpiry)) < 5,
            'Expiry should not change when activating auto-renew on existing subscription'
        );
    }

    public function test_plan_activated_sets_expiry_for_new_subscription(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'xendit_plan_id' => 'repl_new_exp',
            'recurring_status' => 'REQUIRES_ACTION',
        ]);

        $request = Request::create('/xendit/webhook', 'POST', [
            'event' => 'recurring.plan.activated',
            'data' => ['id' => 'repl_new_exp'],
        ]);
        $request->headers->set('x-callback-token', 'valid-token');

        app(HandleXenditWebhook::class)->execute($request);

        $subscription->refresh();
        $this->assertEquals('ACTIVE', $subscription->recurring_status);
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
        // New subscription should get expires_at set
        $this->assertNotNull($subscription->expires_at);
        $this->assertTrue($subscription->expires_at->isFuture());
    }

    // ─── Controller tests ─────────────────────────────────────────────────────

    public function test_enable_auto_renew_endpoint_returns_linking_url(): void
    {
        $this->mockXendit();

        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_endpoint_test',
            'expires_at' => now()->addDays(20),
        ]);

        $this->actingAs($user)
            ->postJson("/subscriptions/{$subscription->id}/enable-auto-renew")
            ->assertOk()
            ->assertJsonStructure(['linking_url'])
            ->assertJsonPath('linking_url', 'https://linking.xendit.co/auto');
    }

    public function test_enable_auto_renew_rejects_non_owner(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $otherUser->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_other_user',
            'expires_at' => now()->addDays(20),
        ]);

        $this->actingAs($user)
            ->postJson("/subscriptions/{$subscription->id}/enable-auto-renew")
            ->assertForbidden();
    }

    public function test_enable_auto_renew_rejects_already_recurring(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_already',
            'expires_at' => now()->addDays(20),
        ]);

        $this->actingAs($user)
            ->postJson("/subscriptions/{$subscription->id}/enable-auto-renew")
            ->assertStatus(400);
    }

    public function test_enable_creator_plan_auto_renew_endpoint(): void
    {
        $this->mockXendit();

        $user = User::factory()->create();
        CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_PRO,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_creator_endpoint',
            'expires_at' => now()->addDays(15),
        ]);

        $this->actingAs($user)
            ->postJson('/creator/plan/enable-auto-renew')
            ->assertOk()
            ->assertJsonStructure(['linking_url']);
    }

    public function test_creates_recurring_plan_for_creator_pro_subscription(): void
    {
        $this->mockXendit();

        $user = User::factory()->create();
        $creatorSub = CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_PRO,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_creator_pro_legacy',
            'expires_at' => now()->addDays(15),
        ]);

        $action = app(EnableAutoRenew::class);
        $linkingUrl = $action->executeForCreatorPlan($creatorSub);

        $this->assertEquals('https://linking.xendit.co/auto', $linkingUrl);

        $creatorSub->refresh();
        $this->assertEquals('repl_auto_001', $creatorSub->xendit_plan_id);
        $this->assertEquals('REQUIRES_ACTION', $creatorSub->recurring_status);
    }

    public function test_throws_if_creator_plan_already_recurring(): void
    {
        $user = User::factory()->create();
        $creatorSub = CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_BASIC,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_existing_creator',
            'expires_at' => now()->addDays(15),
        ]);

        $action = app(EnableAutoRenew::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already has auto-renew');
        $action->executeForCreatorPlan($creatorSub);
    }

    public function test_rethrows_xendit_api_failure_for_subscription(): void
    {
        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createCustomer')
            ->andReturn(['id' => 'cust_fail']);
        $xendit->shouldReceive('createRecurringPlan')
            ->andThrow(new \RuntimeException('Xendit create plan failed'));

        $this->app->instance(XenditService::class, $xendit);

        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_fail_test',
            'expires_at' => now()->addDays(20),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Xendit create plan failed');

        $action = app(EnableAutoRenew::class);
        $action->executeForSubscription($subscription);
    }

    public function test_returns_empty_string_when_no_actions_url(): void
    {
        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createCustomer')
            ->andReturn(['id' => 'cust_no_url']);
        $xendit->shouldReceive('createRecurringPlan')
            ->andReturn([
                'id' => 'repl_no_url',
                'status' => 'REQUIRES_ACTION',
                'actions' => [['action' => 'AUTH']],
            ]);

        $this->app->instance(XenditService::class, $xendit);

        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_no_url_test',
            'expires_at' => now()->addDays(20),
        ]);

        $action = app(EnableAutoRenew::class);
        $linkingUrl = $action->executeForSubscription($subscription);

        $this->assertEquals('', $linkingUrl);
    }
}
