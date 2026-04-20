<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Actions\Billing\SyncMembershipFromSubscription;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PromoFirstMonthCheckoutTest extends TestCase
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

    public function test_initial_checkout_uses_first_month_price_when_set(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create(['first_month_price' => 199]);

        $this->xendit->shouldReceive('createInvoice')
            ->once()
            ->withArgs(fn (array $data) => (float) $data['amount'] === 199.0)
            ->andReturn(['id' => 'inv_promo', 'invoice_url' => 'https://checkout.xendit.co/promo']);

        $this->action->execute($user, $community);
    }

    public function test_initial_checkout_falls_back_to_regular_price_without_promo(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create(['first_month_price' => null]);

        $this->xendit->shouldReceive('createInvoice')
            ->once()
            ->withArgs(fn (array $data) => (float) $data['amount'] === 999.0)
            ->andReturn(['id' => 'inv_regular', 'invoice_url' => 'https://checkout.xendit.co/regular']);

        $this->action->execute($user, $community);
    }

    public function test_sync_upgrades_trial_member_to_paid_and_clears_expiry(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at' => now()->subHour(),
        ]);

        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_upgrade',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        (new SyncMembershipFromSubscription)->execute($subscription);

        $member->refresh();
        $this->assertSame(CommunityMember::MEMBERSHIP_PAID, $member->membership_type);
        $this->assertNull($member->expires_at);
        $this->assertSame(CommunityMember::ROLE_MEMBER, $member->role);
    }

    public function test_sync_preserves_admin_role_when_upgrading_trial(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_ADMIN,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at' => now()->subHour(),
        ]);

        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_admin',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        (new SyncMembershipFromSubscription)->execute($subscription);

        $member->refresh();
        $this->assertSame(CommunityMember::ROLE_ADMIN, $member->role);
        $this->assertSame(CommunityMember::MEMBERSHIP_PAID, $member->membership_type);
        $this->assertNull($member->expires_at);
    }
}
