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

    public function test_member_can_create_comment(): void
    {
        $community = Community::factory()->create(['price' => 0]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/comments", ['content' => 'My comment']);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'My comment');
        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'user_id' => $member->id,
            'content' => 'My comment',
        ]);
    }

    public function test_comment_requires_content(): void
    {
        $community = Community::factory()->create(['price' => 0]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);
        $post = Post::factory()->create(['community_id' => $community->id]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/posts/{$post->id}/comments", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_author_can_delete_comment(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'community_id' => $community->id,
            'user_id' => $user->id,
            'content' => 'My comment',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Comment deleted.');

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_non_author_cannot_delete_comment(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);
        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'community_id' => $community->id,
            'user_id' => $author->id,
            'content' => 'Author comment',
        ]);

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/v1/comments/{$comment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_unauthenticated_cannot_comment(): void
    {
        $community = Community::factory()->create(['price' => 0]);
        $post = Post::factory()->create(['community_id' => $community->id]);

        $this->postJson("/api/v1/posts/{$post->id}/comments", ['content' => 'My comment'])
            ->assertUnauthorized();
    }
}
