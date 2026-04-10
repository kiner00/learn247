<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Models\Affiliate;
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
        $this->app->instance(XenditService::class, $this->xendit);
        $this->action = app(StartSubscriptionCheckout::class);
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

    public function test_resolves_valid_affiliate_code_and_links_to_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid(499)->create();

        $affiliateUser = User::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
            'code'         => 'VALID_AFF_CODE',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $this->xendit->shouldReceive('createInvoice')
            ->once()
            ->andReturn(['id' => 'inv_aff_1', 'invoice_url' => 'https://checkout.xendit.co/aff1']);

        $result = $this->action->execute($user, $community, 'VALID_AFF_CODE');

        $this->assertDatabaseHas('subscriptions', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'affiliate_id' => $affiliate->id,
            'status'       => Subscription::STATUS_PENDING,
        ]);
    }

    public function test_ignores_invalid_affiliate_code(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid(499)->create();

        $this->xendit->shouldReceive('createInvoice')
            ->once()
            ->andReturn(['id' => 'inv_no_aff', 'invoice_url' => 'https://checkout.xendit.co/noaff']);

        $result = $this->action->execute($user, $community, 'NONEXISTENT_CODE');

        $this->assertDatabaseHas('subscriptions', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'affiliate_id' => null,
            'status'       => Subscription::STATUS_PENDING,
        ]);
    }

    public function test_uses_custom_success_redirect_url(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid(499)->create();
        $customUrl = 'https://example.com/thank-you';

        $this->xendit->shouldReceive('createInvoice')
            ->once()
            ->withArgs(function (array $data) use ($customUrl) {
                return $data['success_redirect_url'] === $customUrl;
            })
            ->andReturn(['id' => 'inv_redir', 'invoice_url' => 'https://checkout.xendit.co/redir']);

        $result = $this->action->execute($user, $community, null, $customUrl);

        $this->assertEquals('https://checkout.xendit.co/redir', $result['checkout_url']);
    }

    public function test_ignores_affiliate_code_from_different_community(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid(499)->create();
        $otherCommunity = Community::factory()->paid(299)->create();

        $affiliateUser = User::factory()->create();
        Affiliate::create([
            'community_id' => $otherCommunity->id,
            'user_id'      => $affiliateUser->id,
            'code'         => 'OTHER_COMM_AFF',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $this->xendit->shouldReceive('createInvoice')
            ->once()
            ->andReturn(['id' => 'inv_other', 'invoice_url' => 'https://checkout.xendit.co/other']);

        $result = $this->action->execute($user, $community, 'OTHER_COMM_AFF');

        $this->assertDatabaseHas('subscriptions', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'affiliate_id' => null,
        ]);
    }
}
