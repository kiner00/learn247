<?php

namespace Tests\Feature\Policies;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Policies\CommunityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_view_any(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->can('viewAny', Community::class));
    }

    public function test_anyone_can_view(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->assertTrue($user->can('view', $community));
    }

    public function test_guest_can_view(): void
    {
        $community = Community::factory()->create();
        $this->assertTrue((new CommunityPolicy)->view(null, $community));
    }

    public function test_any_user_can_create(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->can('create', Community::class));
    }

    public function test_owner_can_update(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $this->assertTrue($owner->can('update', $community));
    }

    public function test_non_owner_cannot_update(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->assertFalse($user->can('update', $community));
    }

    public function test_owner_can_delete(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $this->assertTrue($owner->can('delete', $community));
    }

    public function test_non_owner_cannot_delete(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->assertFalse($user->can('delete', $community));
    }

    public function test_owner_can_view_analytics(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $this->assertTrue($owner->can('viewAnalytics', $community));
    }

    public function test_admin_can_view_analytics(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($user->can('viewAnalytics', $community));
    }

    public function test_regular_member_cannot_view_analytics(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertFalse($user->can('viewAnalytics', $community));
    }

    public function test_admin_can_manage_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($user->can('manageMember', $community));
    }

    public function test_moderator_can_manage_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->moderator()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($user->can('manageMember', $community));
    }

    public function test_regular_member_cannot_manage_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertFalse($user->can('manageMember', $community));
    }

    // ─── manage ──────────────────────────────────────────────────────────────

    public function test_owner_can_manage(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $this->assertTrue($owner->can('manage', $community));
    }

    public function test_super_admin_can_manage(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $community = Community::factory()->create();
        $this->assertTrue($admin->can('manage', $community));
    }

    public function test_admin_member_can_manage(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($user->can('manage', $community));
    }

    public function test_regular_member_cannot_manage(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertFalse($user->can('manage', $community));
    }

    public function test_moderator_cannot_manage(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->moderator()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertFalse($user->can('manage', $community));
    }

    // ─── moderate ────────────────────────────────────────────────────────────

    public function test_owner_can_moderate(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $this->assertTrue($owner->can('moderate', $community));
    }

    public function test_super_admin_can_moderate(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $community = Community::factory()->create();
        $this->assertTrue($admin->can('moderate', $community));
    }

    public function test_admin_member_can_moderate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($user->can('moderate', $community));
    }

    public function test_moderator_can_moderate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->moderator()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($user->can('moderate', $community));
    }

    public function test_regular_member_cannot_moderate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertFalse($user->can('moderate', $community));
    }

    public function test_non_member_cannot_moderate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->assertFalse($user->can('moderate', $community));
    }

    public function test_non_member_cannot_view_analytics(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->assertFalse($user->can('viewAnalytics', $community));
    }

    public function test_non_member_cannot_manage_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->assertFalse($user->can('manageMember', $community));
    }

    // ─── view (private community) ────────────────────────────────────────────

    public function test_guest_cannot_view_private_community(): void
    {
        $community = Community::factory()->create(['is_private' => true]);
        $this->assertFalse((new CommunityPolicy)->view(null, $community));
    }

    public function test_non_member_cannot_view_private_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['is_private' => true]);
        $this->assertFalse($user->can('view', $community));
    }

    public function test_owner_can_view_private_community(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['is_private' => true, 'owner_id' => $owner->id]);
        $this->assertTrue($owner->can('view', $community));
    }

    public function test_member_can_view_private_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['is_private' => true]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($user->can('view', $community));
    }

    public function test_super_admin_can_view_private_community(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $community = Community::factory()->create(['is_private' => true]);
        $this->assertTrue($admin->can('view', $community));
    }

    // ─── super admin for update/delete ───────────────────────────────────────

    public function test_super_admin_can_update(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $community = Community::factory()->create();
        $this->assertTrue($admin->can('update', $community));
    }

    public function test_super_admin_can_delete(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $community = Community::factory()->create();
        $this->assertTrue($admin->can('delete', $community));
    }

    // ─── owner for manageMember ──────────────────────────────────────────────

    public function test_owner_without_membership_cannot_manage_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        // Owner is not a CommunityMember row, so manageMember checks membership only
        $this->assertFalse($owner->can('manageMember', $community));
    }
}
