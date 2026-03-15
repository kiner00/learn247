<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_subscription_and_returns_url(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid(499)->create();

        $this->mock(XenditService::class, function ($mock): void {
            $mock->shouldReceive('createInvoice')
                ->once()
                ->andReturn([
                    'id'          => 'xendit_inv_123',
                    'invoice_url' => 'https://checkout.xendit.co/example',
                ]);
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/checkout");

        $response->assertOk()
            ->assertJsonStructure(['checkout_url', 'subscription_id'])
            ->assertJsonPath('checkout_url', 'https://checkout.xendit.co/example');

        $this->assertDatabaseHas('subscriptions', [
            'community_id' => $community->id,
            'user_id'     => $user->id,
            'status'      => Subscription::STATUS_PENDING,
        ]);
    }

    public function test_checkout_free_community_returns_422(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/checkout");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['community']);
    }

    public function test_checkout_with_existing_active_subscription_returns_422(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid(499)->create();
        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'     => $user->id,
            'status'      => Subscription::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/checkout");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subscription']);
    }

    public function test_unauthenticated_cannot_checkout(): void
    {
        $community = Community::factory()->paid(499)->create();

        $this->postJson("/api/communities/{$community->slug}/checkout")
            ->assertUnauthorized();
    }
}
