<?php

namespace Tests\Feature\Api;

use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LikeControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── togglePost ───────────────────────────────────────────────────────────

    public function test_authenticated_user_can_like_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/posts/{$post->id}/like");

        $response->assertOk()
            ->assertJsonPath('action', 'added')
            ->assertJsonPath('likes_count', 1);

        $this->assertDatabaseHas('likes', [
            'user_id'       => $user->id,
            'likeable_type' => Post::class,
            'likeable_id'   => $post->id,
        ]);
    }

    public function test_post_like_toggle_like_then_unlike(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)->postJson("/api/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('action', 'added')
            ->assertJsonPath('likes_count', 1);

        $this->actingAs($user)->postJson("/api/posts/{$post->id}/like")
            ->assertOk()
            ->assertJsonPath('action', 'removed')
            ->assertJsonPath('likes_count', 0);

        $this->assertDatabaseMissing('likes', [
            'user_id'       => $user->id,
            'likeable_type' => Post::class,
            'likeable_id'   => $post->id,
        ]);
    }

    // ─── toggleComment ─────────────────────────────────────────────────────────

    public function test_authenticated_user_can_like_a_comment(): void
    {
        $user    = User::factory()->create();
        $comment = Comment::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/comments/{$comment->id}/like");

        $response->assertOk()
            ->assertJsonPath('action', 'added')
            ->assertJsonPath('likes_count', 1);

        $this->assertDatabaseHas('likes', [
            'user_id'       => $user->id,
            'likeable_type' => Comment::class,
            'likeable_id'   => $comment->id,
        ]);
    }

    // ─── togglePin ────────────────────────────────────────────────────────────

    public function test_community_owner_can_pin_post(): void
    {
        $owner    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $post      = Post::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/api/posts/{$post->id}/pin");

        $response->assertOk()
            ->assertJsonPath('is_pinned', true)
            ->assertJsonPath('message', 'Post pinned.');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'is_pinned' => true]);
    }

    public function test_non_owner_cannot_pin_post(): void
    {
        $owner     = User::factory()->create();
        $nonOwner  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $post      = Post::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $nonOwner->id]);

        $this->actingAs($nonOwner)
            ->postJson("/api/posts/{$post->id}/pin")
            ->assertForbidden();
    }

    public function test_owner_can_toggle_pin_on_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $post      = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'is_pinned'    => false,
        ]);

        $this->actingAs($owner)
            ->postJson("/api/posts/{$post->id}/pin")
            ->assertOk()
            ->assertJsonPath('is_pinned', true);
    }

    // ─── Unauthenticated ──────────────────────────────────────────────────────

    public function test_unauthenticated_returns_401_for_like_post(): void
    {
        $post = Post::factory()->create();

        $this->postJson("/api/posts/{$post->id}/like")
            ->assertUnauthorized();
    }

    public function test_unauthenticated_returns_401_for_like_comment(): void
    {
        $comment = Comment::factory()->create();

        $this->postJson("/api/comments/{$comment->id}/like")
            ->assertUnauthorized();
    }

    public function test_unauthenticated_returns_401_for_pin_post(): void
    {
        $post = Post::factory()->create();

        $this->postJson("/api/posts/{$post->id}/pin")
            ->assertUnauthorized();
    }
}
