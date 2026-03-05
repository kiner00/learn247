<?php

namespace Tests\Feature\Web;

use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_member_can_post_comment(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/comments", ['content' => 'Hello!'])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', ['post_id' => $post->id, 'content' => 'Hello!']);
    }

    public function test_member_can_reply_to_comment(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);
        $parent    = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/comments", ['content' => 'Reply!', 'parent_id' => $parent->id])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', ['parent_id' => $parent->id, 'content' => 'Reply!']);
    }

    public function test_store_requires_content(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/comments", [])
            ->assertSessionHasErrors(['content']);
    }

    public function test_store_rejects_nonexistent_parent_id(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/comments", ['content' => 'Hi', 'parent_id' => 99999])
            ->assertSessionHasErrors(['parent_id']);
    }

    public function test_unauthenticated_user_cannot_post_comment(): void
    {
        $post = Post::factory()->create();

        $this->post("/posts/{$post->id}/comments", ['content' => 'Hi'])
            ->assertRedirect('/login');
    }

    public function test_non_member_gets_403_when_posting_comment(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/comments", ['content' => 'Hi'])
            ->assertForbidden();
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_author_can_delete_own_comment(): void
    {
        $user    = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete("/comments/{$comment->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_unauthenticated_user_cannot_delete_comment(): void
    {
        $comment = Comment::factory()->create();

        $this->delete("/comments/{$comment->id}")
            ->assertRedirect('/login');
    }
}
