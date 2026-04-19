<?php

namespace Tests\Feature\Actions\Affiliate;

use App\Actions\Affiliate\JoinAffiliate;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JoinAffiliateTest extends TestCase
{
    use RefreshDatabase;

    private JoinAffiliate $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new JoinAffiliate;
    }

    public function test_success_with_active_subscription_and_affiliate_program(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $affiliate = $this->action->execute($user, $community);

        $this->assertInstanceOf(Affiliate::class, $affiliate);
        $this->assertEquals($community->id, $affiliate->community_id);
        $this->assertEquals($user->id, $affiliate->user_id);
        $this->assertEquals(Affiliate::STATUS_ACTIVE, $affiliate->status);
        $this->assertNotEmpty($affiliate->code);
        $this->assertDatabaseHas('affiliates', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_throws_if_no_affiliate_program(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => null]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This community does not have an affiliate program.');
        $this->action->execute($user, $community);
    }

    public function test_throws_if_not_subscribed(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You must be subscribed to this community to become an affiliate.');
        $this->action->execute($user, $community);
    }

    public function test_throws_if_already_affiliate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
        Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'code' => 'existing-code',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You are already an affiliate for this community.');
        $this->action->execute($user, $community);
    }
}
