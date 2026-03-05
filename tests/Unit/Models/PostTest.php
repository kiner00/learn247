<?php

namespace Tests\Unit\Models;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_has_likes_relation(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Like::create(['user_id' => $user->id, 'likeable_type' => Post::class, 'likeable_id' => $post->id]);

        $this->assertCount(1, $post->likes);
    }

    public function test_post_likes_count_is_zero_by_default(): void
    {
        $post = Post::factory()->create();

        $this->assertCount(0, $post->likes);
    }

    public function test_post_belongs_to_community(): void
    {
        $post = Post::factory()->create();

        $this->assertNotNull($post->community);
    }

    public function test_post_belongs_to_author(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($post->author->is($user));
    }
}
