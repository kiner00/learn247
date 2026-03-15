<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->get('/communities');

        $response->assertOk();
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_create_community(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/communities', [
            'name'        => 'My Community',
            'description' => 'A test community.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('communities', ['name' => 'My Community', 'owner_id' => $user->id]);
    }

    public function test_unauthenticated_user_cannot_create_community(): void
    {
        $this->post('/communities', ['name' => 'Test'])
            ->assertRedirect('/login');
    }

    public function test_create_community_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/communities', [])
            ->assertSessionHasErrors(['name']);
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_a_public_community(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['is_private' => false]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}")
            ->assertOk();
    }

    public function test_unauthenticated_user_can_view_public_community(): void
    {
        $community = Community::factory()->create(['is_private' => false]);

        $this->get("/communities/{$community->slug}")
            ->assertOk();
    }

    public function test_private_community_show_page_is_accessible(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'is_private' => true]);

        $this->actingAs($other)
            ->get("/communities/{$community->slug}")
            ->assertOk();
    }

    // ─── join ─────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_join_free_community(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->post("/communities/{$community->slug}/join")
            ->assertRedirect();

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_join(): void
    {
        $community = Community::factory()->create(['price' => 0]);

        $this->post("/communities/{$community->slug}/join")
            ->assertRedirect('/login');
    }

    // ─── members ──────────────────────────────────────────────────────────────

    public function test_member_can_view_members_page(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}/members")
            ->assertOk();
    }

    // ─── settings ─────────────────────────────────────────────────────────────

    public function test_owner_can_view_settings_page(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings")
            ->assertOk();
    }

    public function test_non_owner_cannot_view_settings_page(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $this->actingAs($other)
            ->get("/communities/{$community->slug}/settings")
            ->assertForbidden();
    }

    // ─── update ────────────────────────────────────────────────────────────────

    public function test_owner_can_update_community(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->patch("/communities/{$community->slug}", [
            'name' => 'Updated Community Name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('communities', ['id' => $community->id, 'name' => 'Updated Community Name']);
    }

    public function test_non_owner_cannot_update_community(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->patch("/communities/{$community->slug}", ['name' => 'Hacked Name'])
            ->assertForbidden();

        $this->assertDatabaseHas('communities', ['id' => $community->id, 'name' => $community->name]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_owner_can_delete_community(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->delete("/communities/{$community->slug}");

        $response->assertRedirect(route('communities.index'));
        $this->assertSoftDeleted('communities', ['id' => $community->id]);
    }

    public function test_non_owner_cannot_delete_community(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->delete("/communities/{$community->slug}")
            ->assertForbidden();

        $this->assertDatabaseHas('communities', ['id' => $community->id]);
    }

    // ─── about ──────────────────────────────────────────────────────────────────

    public function test_about_page_returns_200(): void
    {
        $community = Community::factory()->create();

        $response = $this->get("/communities/{$community->slug}/about");

        $response->assertOk();
    }

    // ─── analytics ─────────────────────────────────────────────────────────────

    public function test_owner_can_view_analytics_page(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)->get("/communities/{$community->slug}/analytics");

        $response->assertOk();
    }
}
