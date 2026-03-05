<?php

namespace Tests\Unit\Models;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_like_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $like = Like::create(['user_id' => $user->id, 'likeable_type' => Post::class, 'likeable_id' => $post->id]);

        $this->assertTrue($like->user->is($user));
    }

    public function test_likeable_resolves_to_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $like = Like::create(['user_id' => $user->id, 'likeable_type' => Post::class, 'likeable_id' => $post->id]);

        $this->assertInstanceOf(Post::class, $like->likeable);
        $this->assertTrue($like->likeable->is($post));
    }

    public function test_likeable_resolves_to_comment(): void
    {
        $user    = User::factory()->create();
        $comment = Comment::factory()->create();
        $like    = Like::create(['user_id' => $user->id, 'likeable_type' => Comment::class, 'likeable_id' => $comment->id]);

        $this->assertInstanceOf(Comment::class, $like->likeable);
        $this->assertTrue($like->likeable->is($comment));
    }
}
