<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_members(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson("/api/community-members?community_slug={$community->slug}")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_unauthenticated_user_cannot_list_members(): void
    {
        $community = Community::factory()->create();

        $this->getJson("/api/community-members?community_slug={$community->slug}")
            ->assertUnauthorized();
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_admin_can_remove_member(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $this->actingAs($owner)
            ->deleteJson("/api/communities/{$community->slug}/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Member removed.');

        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);
    }

    public function test_regular_member_cannot_remove_another_member(): void
    {
        $owner   = User::factory()->create();
        $actor   = User::factory()->create();
        $target  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $actor->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $target->id]);

        $this->actingAs($actor)
            ->deleteJson("/api/communities/{$community->slug}/members/{$target->id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_remove_member(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->deleteJson("/api/communities/{$community->slug}/members/{$member->id}")
            ->assertUnauthorized();
    }

    // ─── changeRole ───────────────────────────────────────────────────────────

    public function test_owner_can_promote_member_to_moderator(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $this->actingAs($owner)
            ->patchJson("/api/communities/{$community->slug}/members/{$member->id}/role", ['role' => 'moderator'])
            ->assertOk()
            ->assertJsonPath('data.role', 'moderator');
    }

    public function test_non_owner_cannot_change_role(): void
    {
        $owner   = User::factory()->create();
        $actor   = User::factory()->create();
        $target  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $actor->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $target->id]);

        $this->actingAs($actor)
            ->patchJson("/api/communities/{$community->slug}/members/{$target->id}/role", ['role' => 'moderator'])
            ->assertForbidden();
    }

    public function test_change_role_requires_valid_role(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $this->actingAs($owner)
            ->patchJson("/api/communities/{$community->slug}/members/{$member->id}/role", ['role' => 'superuser'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_unauthenticated_user_cannot_change_role(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->patchJson("/api/communities/{$community->slug}/members/{$member->id}/role", ['role' => 'moderator'])
            ->assertUnauthorized();
    }
}
