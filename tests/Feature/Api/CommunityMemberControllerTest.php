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

    public function test_user_can_list_community_members(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->count(2)->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/community-members?community_slug={$community->slug}");

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_admin_can_remove_member(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);
        $memberToRemove = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $memberToRemove->id]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/communities/{$community->slug}/members/{$memberToRemove->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Member removed.');

        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'user_id'      => $memberToRemove->id,
        ]);
    }

    public function test_regular_member_cannot_remove_member(): void
    {
        $owner         = User::factory()->create();
        $regularMember = User::factory()->create();
        $community     = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $regularMember->id]);
        $targetMember = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $targetMember->id]);

        $this->actingAs($regularMember, 'sanctum')
            ->deleteJson("/api/communities/{$community->slug}/members/{$targetMember->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $targetMember->id,
        ]);
    }

    public function test_owner_can_change_member_role(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/communities/{$community->slug}/members/{$member->id}/role", [
                'role' => 'moderator',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'moderator');

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => 'moderator',
        ]);
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $community = Community::factory()->create();

        $this->getJson("/api/community-members?community_slug={$community->slug}")
            ->assertUnauthorized();

        $this->deleteJson("/api/communities/{$community->slug}/members/1")
            ->assertUnauthorized();

        $this->patchJson("/api/communities/{$community->slug}/members/1/role", ['role' => 'admin'])
            ->assertUnauthorized();
    }
}
