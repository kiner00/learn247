<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class StartSubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private XenditService $xendit;
    private StartSubscriptionCheckout $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->xendit = Mockery::mock(XenditService::class);
        $this->action = new StartSubscriptionCheckout($this->xendit);
    }

    public function test_creates_pending_subscription_and_returns_checkout_url(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid(499)->create();

        $this->xendit->shouldReceive('createInvoice')
            ->once()
            ->andReturn(['id' => 'inv_123', 'invoice_url' => 'https://checkout.xendit.co/abc']);

        $result = $this->action->execute($user, $community);

        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertArrayHasKey('subscription', $result);
        $this->assertEquals('https://checkout.xendit.co/abc', $result['checkout_url']);
        $this->assertDatabaseHas('subscriptions', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_PENDING,
            'xendit_id'    => 'inv_123',
        ]);
    }

    public function test_throws_validation_exception_for_free_community(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $community);
    }

    public function test_throws_if_active_subscription_exists(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();
        Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $community);
    }
}
