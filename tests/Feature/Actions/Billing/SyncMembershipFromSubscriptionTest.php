<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\SyncMembershipFromSubscription;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncMembershipFromSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private SyncMembershipFromSubscription $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new SyncMembershipFromSubscription();
    }

    public function test_active_subscription_creates_member_row(): void
    {
        $user         = User::factory()->create();
        $community    = Community::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $this->action->execute($subscription);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);
    }

    public function test_active_subscription_does_not_duplicate_existing_member(): void
    {
        $user         = User::factory()->create();
        $community    = Community::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->action->execute($subscription);

        $this->assertCount(1, CommunityMember::where([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ])->get());
    }

    public function test_inactive_subscription_removes_member_role(): void
    {
        $user         = User::factory()->create();
        $community    = Community::factory()->create();
        $subscription = Subscription::factory()->expired()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->action->execute($subscription);

        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);
    }

    public function test_inactive_subscription_preserves_admin_role(): void
    {
        $user         = User::factory()->create();
        $community    = Community::factory()->create();
        $subscription = Subscription::factory()->expired()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $this->action->execute($subscription);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'role'         => CommunityMember::ROLE_ADMIN,
        ]);
    }
}
