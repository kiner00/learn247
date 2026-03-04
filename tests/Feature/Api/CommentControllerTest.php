<?php

namespace Tests\Feature\Api;

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

    public function test_member_can_comment_on_post(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/posts/{$post->id}/comments", [
            'content' => 'Nice post!',
        ]);

        $response->assertCreated()->assertJsonPath('data.content', 'Nice post!');
    }

    public function test_unauthenticated_user_cannot_comment(): void
    {
        $post = Post::factory()->create();

        $this->postJson("/api/posts/{$post->id}/comments", ['content' => 'Hi'])
            ->assertUnauthorized();
    }

    public function test_comment_requires_content(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)->postJson("/api/posts/{$post->id}/comments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_author_can_delete_comment(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);
        $comment   = Comment::factory()->create([
            'post_id'      => $post->id,
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $this->actingAs($user)->deleteJson("/api/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Comment deleted.');

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_non_author_cannot_delete_comment(): void
    {
        $community = Community::factory()->create();
        $author    = User::factory()->create();
        $other     = User::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);
        $comment   = Comment::factory()->create([
            'post_id'      => $post->id,
            'community_id' => $community->id,
            'user_id'      => $author->id,
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $this->actingAs($other)->deleteJson("/api/comments/{$comment->id}")
            ->assertForbidden();
    }
}
