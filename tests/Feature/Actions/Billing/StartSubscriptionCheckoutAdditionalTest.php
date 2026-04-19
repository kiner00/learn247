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

class StartSubscriptionCheckoutAdditionalTest extends TestCase
{
    use RefreshDatabase;

    private XenditService $xendit;

    private StartSubscriptionCheckout $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->xendit = Mockery::mock(XenditService::class);
        $this->app->instance(XenditService::class, $this->xendit);
        $this->action = app(StartSubscriptionCheckout::class);
    }

    public function test_xendit_api_failure_is_rethrown(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create();

        $this->xendit->shouldReceive('createInvoice')
            ->once()
            ->andThrow(new \RuntimeException('Xendit API is down'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Xendit API is down');

        $this->action->execute($user, $community);
    }

    public function test_uses_default_success_url_when_none_provided(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create();

        $this->xendit->shouldReceive('createInvoice')
            ->once()
            ->withArgs(function (array $data) use ($community) {
                return str_contains($data['success_redirect_url'], "/communities/{$community->slug}");
            })
            ->andReturn(['id' => 'inv_default', 'invoice_url' => 'https://checkout.xendit.co/default']);

        $result = $this->action->execute($user, $community);

        $this->assertEquals('https://checkout.xendit.co/default', $result['checkout_url']);
    }

    public function test_allows_checkout_when_existing_subscription_is_not_active(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(499)->create();

        // Existing but expired subscription
        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_EXPIRED,
        ]);

        $this->xendit->shouldReceive('createInvoice')
            ->once()
            ->andReturn(['id' => 'inv_re', 'invoice_url' => 'https://checkout.xendit.co/re']);

        $result = $this->action->execute($user, $community);

        $this->assertArrayHasKey('checkout_url', $result);
    }
}
