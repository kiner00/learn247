<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_remove_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
            'role'         => CommunityMember::ROLE_ADMIN,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($admin)
            ->delete(route('communities.members.destroy', [$community, $member]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Member removed.');

        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);
    }

    public function test_regular_member_cannot_remove_another_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $actor = User::factory()->create();
        $target = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $actor->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $target->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($actor)
            ->delete(route('communities.members.destroy', [$community, $target]))
            ->assertForbidden();
    }

    public function test_cannot_remove_community_owner(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
            'role'         => CommunityMember::ROLE_ADMIN,
        ]);

        $this->actingAs($admin)
            ->delete(route('communities.members.destroy', [$community, $owner]))
            ->assertForbidden();
    }

    public function test_guest_cannot_remove_member(): void
    {
        $community = Community::factory()->create();
        $member = User::factory()->create();

        $this->delete(route('communities.members.destroy', [$community, $member]))
            ->assertRedirect('/login');
    }

    // ── changeRole ────────────────────────────────────────────────────────────

    public function test_owner_can_change_member_role(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.role', [$community, $member]), ['role' => 'admin'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_ADMIN,
        ]);
    }

    public function test_non_owner_cannot_change_role(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
            'role'         => CommunityMember::ROLE_ADMIN,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($admin)
            ->patch(route('communities.members.role', [$community, $member]), ['role' => 'moderator'])
            ->assertForbidden();
    }

    public function test_change_role_validates_role_field(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.role', [$community, $member]), ['role' => 'superadmin'])
            ->assertSessionHasErrors('role');
    }

    public function test_change_role_requires_role_field(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.role', [$community, $member]), [])
            ->assertSessionHasErrors('role');
    }
}
