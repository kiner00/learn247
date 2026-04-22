<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_subscription_and_returns_url(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);

        $this->mock(XenditService::class, function ($mock): void {
            $mock->shouldReceive('createInvoice')
                ->once()
                ->andReturn([
                    'id' => 'xendit_inv_123',
                    'invoice_url' => 'https://checkout.xendit.co/example',
                ]);
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/checkout");

        $response->assertOk()
            ->assertJsonStructure(['checkout_url', 'subscription_id'])
            ->assertJsonPath('checkout_url', 'https://checkout.xendit.co/example');

        $this->assertDatabaseHas('subscriptions', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
        ]);
    }

    public function test_checkout_free_community_returns_422(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/checkout");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['community']);
    }

    public function test_checkout_with_existing_active_subscription_returns_422(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);
        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/checkout");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subscription']);
    }

    public function test_unauthenticated_cannot_checkout(): void
    {
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);

        $this->postJson("/api/v1/communities/{$community->slug}/checkout")
            ->assertUnauthorized();
    }

    public function test_checkout_with_affiliate_code(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'code' => 'REF123ABC',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->mock(XenditService::class, function ($mock): void {
            $mock->shouldReceive('createInvoice')
                ->once()
                ->andReturn([
                    'id' => 'xendit_inv_aff',
                    'invoice_url' => 'https://checkout.xendit.co/aff',
                ]);
        });

        $response = $this->actingAs($user, 'sanctum')
            ->withCookie('ref_code', 'REF123ABC')
            ->postJson("/api/v1/communities/{$community->slug}/checkout");

        $response->assertOk();
    }

    // ─── check-status ────────────────────────────────────────────────────────

    public function test_check_status_returns_current_subscription_state(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_plan_id' => 'repl_abc',
            'recurring_status' => 'ACTIVE',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/check-status")
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonPath('data.status', Subscription::STATUS_ACTIVE)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_recurring', true)
            ->assertJsonPath('data.recurring_status', 'ACTIVE');
    }

    public function test_check_status_returns_pending_for_new_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/check-status")
            ->assertOk()
            ->assertJsonPath('data.status', Subscription::STATUS_PENDING)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_check_status_requires_auth(): void
    {
        $subscription = Subscription::factory()->create();

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/check-status")
            ->assertUnauthorized();
    }

    public function test_check_status_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/check-status")
            ->assertForbidden();
    }

    public function test_check_status_404s_for_unknown_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/subscriptions/999999/check-status')
            ->assertNotFound();
    }

    // ─── cancel-recurring ────────────────────────────────────────────────────

    public function test_cancel_recurring_deactivates_plan_and_returns_status(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_plan_id' => 'repl_cancel_api',
            'recurring_status' => 'ACTIVE',
        ]);

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('deactivateRecurringPlan')
            ->once()
            ->with('repl_cancel_api')
            ->andReturn(['status' => 'INACTIVE']);
        $this->app->instance(XenditService::class, $xendit);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonPath('data.recurring_status', 'INACTIVE')
            ->assertJsonPath('data.is_recurring', true);

        $this->assertEquals('INACTIVE', $subscription->fresh()->recurring_status);
    }

    public function test_cancel_recurring_requires_auth(): void
    {
        $subscription = Subscription::factory()->create(['xendit_plan_id' => 'repl_x']);

        $this->postJson("/api/v1/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertUnauthorized();
    }

    public function test_cancel_recurring_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'user_id' => $owner->id,
            'xendit_plan_id' => 'repl_owned',
            'recurring_status' => 'ACTIVE',
        ]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertForbidden();
    }

    public function test_cancel_recurring_rejects_non_recurring_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'user_id' => $user->id,
            'xendit_plan_id' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertStatus(400);
    }
}
