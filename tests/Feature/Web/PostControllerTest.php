<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_member_can_create_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/posts", [
                'content' => 'Hello community!',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'content'      => 'Hello community!',
        ]);
    }

    public function test_store_requires_content(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/posts", [
                'content' => '',
            ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_store_with_title_and_content(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/posts", [
                'title'   => 'My First Post',
                'content' => 'With a title!',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'community_id' => $community->id,
            'title'        => 'My First Post',
            'content'      => 'With a title!',
        ]);
    }

    public function test_guest_cannot_create_post(): void
    {
        $community = Community::factory()->create();

        $response = $this->post("/communities/{$community->slug}/posts", [
            'content' => 'Should not work',
        ]);

        $response->assertRedirect('/login');
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_author_can_delete_own_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $response = $this->actingAs($member)
            ->delete("/posts/{$post->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_admin_member_can_delete_others_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        $admin     = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $admin->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $response = $this->actingAs($admin)
            ->delete("/posts/{$post->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_regular_member_cannot_delete_others_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $author    = User::factory()->create();
        $other     = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $author->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $author->id,
        ]);

        $response = $this->actingAs($other)
            ->delete("/posts/{$post->id}");

        $response->assertForbidden();
    }

    // ─── togglePin ────────────────────────────────────────────────────────────

    public function test_owner_can_pin_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'is_pinned'    => false,
        ]);

        $response = $this->actingAs($owner)
            ->post("/posts/{$post->id}/pin");

        $response->assertRedirect();
        $this->assertTrue($post->fresh()->is_pinned);
    }

    public function test_owner_can_unpin_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'is_pinned'    => true,
        ]);

        $response = $this->actingAs($owner)
            ->post("/posts/{$post->id}/pin");

        $response->assertRedirect();
        $this->assertFalse($post->fresh()->is_pinned);
    }

    public function test_admin_member_can_pin_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin     = User::factory()->create();
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $admin->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'is_pinned'    => false,
        ]);

        $response = $this->actingAs($admin)
            ->post("/posts/{$post->id}/pin");

        $response->assertRedirect();
        $this->assertTrue($post->fresh()->is_pinned);
    }

    public function test_regular_member_cannot_pin_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'is_pinned'    => false,
        ]);

        $response = $this->actingAs($member)
            ->post("/posts/{$post->id}/pin");

        $response->assertForbidden();
    }
}
