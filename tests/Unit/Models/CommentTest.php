<?php

namespace Tests\Unit\Models;

use App\Models\Comment;
use App\Models\Community;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_belongs_to_post(): void
    {
        $post    = Post::factory()->create();
        $comment = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $post->community_id]);

        $this->assertTrue($comment->post->is($post));
    }

    public function test_comment_belongs_to_community(): void
    {
        $community = Community::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);
        $comment   = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $community->id]);

        $this->assertTrue($comment->community->is($community));
    }

    public function test_comment_belongs_to_author(): void
    {
        $user    = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($comment->author->is($user));
    }

    public function test_comment_can_have_parent(): void
    {
        $post   = Post::factory()->create();
        $parent = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $post->community_id]);
        $reply  = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $post->community_id, 'parent_id' => $parent->id]);

        $this->assertTrue($reply->parent->is($parent));
    }

    public function test_comment_has_replies(): void
    {
        $post   = Post::factory()->create();
        $parent = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $post->community_id]);
        $reply  = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $post->community_id, 'parent_id' => $parent->id]);

        $this->assertTrue($parent->replies->contains($reply));
    }

    public function test_top_level_comment_has_null_parent(): void
    {
        $comment = Comment::factory()->create();

        $this->assertNull($comment->parent_id);
        $this->assertNull($comment->parent);
    }

    public function test_comment_has_likes_relation(): void
    {
        $user    = User::factory()->create();
        $comment = Comment::factory()->create();
        Like::create(['user_id' => $user->id, 'likeable_type' => Comment::class, 'likeable_id' => $comment->id]);

        $this->assertCount(1, $comment->likes);
    }
}
