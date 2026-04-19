<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\SyncMembershipFromSubscription;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncMembershipFromSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscription_creates_membership(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_sync_active',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $action = new SyncMembershipFromSubscription;
        $action->execute($subscription);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_inactive_subscription_removes_regular_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_MEMBER,
        ]);

        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_sync_expired',
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        $action = new SyncMembershipFromSubscription;
        $action->execute($subscription);

        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_inactive_subscription_does_not_remove_admin(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_ADMIN,
        ]);

        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_sync_admin',
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        $action = new SyncMembershipFromSubscription;
        $action->execute($subscription);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_ADMIN,
        ]);
    }

    public function test_active_subscription_reactivates_suspended_affiliate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'code' => 'test-reactivate',
            'status' => Affiliate::STATUS_INACTIVE,
        ]);

        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_sync_reactivate',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $action = new SyncMembershipFromSubscription;
        $action->execute($subscription);

        $this->assertDatabaseHas('affiliates', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
    }

    public function test_inactive_subscription_suspends_active_affiliate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'code' => 'test-suspend',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_sync_suspend',
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        $action = new SyncMembershipFromSubscription;
        $action->execute($subscription);

        $this->assertDatabaseHas('affiliates', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Affiliate::STATUS_INACTIVE,
        ]);
    }
}
