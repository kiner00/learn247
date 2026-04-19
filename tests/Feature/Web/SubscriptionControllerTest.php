<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── checkout ─────────────────────────────────────────────────────────────

    public function test_checkout_redirects_to_xendit_invoice_url(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);

        $invoiceUrl = 'https://checkout.xendit.co/test-invoice-123';

        $this->mock(XenditService::class, function (MockInterface $mock) use ($invoiceUrl) {
            $mock->shouldReceive('createInvoice')
                ->once()
                ->andReturn([
                    'id' => 'inv_test_123',
                    'invoice_url' => $invoiceUrl,
                ]);
        });

        $response = $this->actingAs($user)
            ->post("/communities/{$community->slug}/checkout");

        $response->assertRedirect($invoiceUrl);
    }

    public function test_checkout_creates_pending_subscription(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);

        $this->mock(XenditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createInvoice')
                ->once()
                ->andReturn([
                    'id' => 'inv_test_456',
                    'invoice_url' => 'https://checkout.xendit.co/test-456',
                ]);
        });

        $this->actingAs($user)
            ->post("/communities/{$community->slug}/checkout");

        $this->assertDatabaseHas('subscriptions', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'xendit_id' => 'inv_test_456',
        ]);
    }

    public function test_checkout_free_community_returns_validation_error(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->actingAs($user)
            ->post("/communities/{$community->slug}/checkout");

        $response->assertSessionHasErrors('community');
    }

    public function test_checkout_with_existing_active_subscription_returns_error(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post("/communities/{$community->slug}/checkout");

        $response->assertSessionHasErrors('subscription');
    }

    public function test_guest_cannot_checkout(): void
    {
        $community = Community::factory()->paid(499)->create(['billing_type' => 'one_time']);

        $response = $this->post("/communities/{$community->slug}/checkout");

        $response->assertRedirect('/login');
    }

    public function test_checkout_pending_deletion_community_returns_error(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create(['deletion_requested_at' => now()]);

        $response = $this->actingAs($user)
            ->post("/communities/{$community->slug}/checkout");

        $response->assertSessionHas('error');
    }
}
