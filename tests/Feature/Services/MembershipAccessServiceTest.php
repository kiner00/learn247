<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Community\MembershipAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MembershipAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private MembershipAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MembershipAccessService;
    }

    public function test_owner_always_has_access(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->paid(999)->create(['owner_id' => $owner->id]);

        $this->assertTrue($this->service->hasActiveMembership($owner, $community));
    }

    public function test_free_community_member_without_expiry_has_access(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $this->assertTrue($this->service->hasActiveMembership($user, $community));
    }

    public function test_free_community_with_expired_invite_grant_blocks_access(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->service->hasActiveMembership($user, $community));
    }

    public function test_active_trial_grants_access_to_paid_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at' => now()->addDays(5),
        ]);

        $this->assertTrue($this->service->hasActiveMembership($user, $community));
    }

    public function test_expired_trial_blocks_access_to_paid_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->service->hasActiveMembership($user, $community));
    }

    public function test_active_subscription_grants_access_even_with_expired_trial_row(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at' => now()->subDay(),
        ]);
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue($this->service->hasActiveMembership($user, $community));
    }

    public function test_has_expired_trial_returns_true_for_past_free_expiry(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertTrue($this->service->hasExpiredTrial($user, $community));
    }

    public function test_has_expired_trial_returns_false_when_no_member_row(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create();

        $this->assertFalse($this->service->hasExpiredTrial($user, $community));
    }

    public function test_assert_membership_aborts_when_access_denied(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create();

        $this->expectException(HttpException::class);
        $this->service->assertMembership($user, $community);
    }
}
