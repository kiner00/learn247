<?php

namespace Tests\Feature\Web;

use App\Models\Comment;
use App\Models\Like;
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

        $this->actingAs($user)
            ->post("/posts/{$post->id}/like")
            ->assertRedirect();

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
        ]);
    }

    public function test_authenticated_user_can_unlike_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        Like::create(['user_id' => $user->id, 'likeable_type' => Post::class, 'likeable_id' => $post->id]);

        $this->actingAs($user)
            ->post("/posts/{$post->id}/like")
            ->assertRedirect();

        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_like_post(): void
    {
        $post = Post::factory()->create();

        $this->post("/posts/{$post->id}/like")
            ->assertRedirect('/login');
    }

    // ─── toggleComment ────────────────────────────────────────────────────────

    public function test_authenticated_user_can_like_a_comment(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create();

        $this->actingAs($user)
            ->post("/comments/{$comment->id}/like")
            ->assertRedirect();

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likeable_type' => Comment::class,
            'likeable_id' => $comment->id,
        ]);
    }

    public function test_authenticated_user_can_unlike_a_comment(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create();
        Like::create(['user_id' => $user->id, 'likeable_type' => Comment::class, 'likeable_id' => $comment->id]);

        $this->actingAs($user)
            ->post("/comments/{$comment->id}/like")
            ->assertRedirect();

        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'likeable_type' => Comment::class,
            'likeable_id' => $comment->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_like_comment(): void
    {
        $comment = Comment::factory()->create();

        $this->post("/comments/{$comment->id}/like")
            ->assertRedirect('/login');
    }
}
