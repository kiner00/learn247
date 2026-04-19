<?php

namespace Tests\Feature\Actions\Feed;

use App\Actions\Feed\ToggleLike;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToggleLikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_like(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $action = new ToggleLike;

        $result = $action->execute($user, $post);

        $this->assertEquals('added', $result['action']);
        $this->assertEquals('like', $result['type']);
        $this->assertEquals(1, $result['likes_count']);
    }

    public function test_remove_like(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $action = new ToggleLike;

        $action->execute($user, $post);
        $result = $action->execute($user, $post);

        $this->assertEquals('removed', $result['action']);
        $this->assertEquals(0, $result['likes_count']);
    }

    public function test_update_reaction_type(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $action = new ToggleLike;

        $action->execute($user, $post, 'like');
        $result = $action->execute($user, $post, 'trophy');

        $this->assertEquals('updated', $result['action']);
        $this->assertEquals('trophy', $result['type']);
        $this->assertEquals(1, $result['likes_count']);
    }

    public function test_invalid_type_defaults_to_like(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $action = new ToggleLike;

        $result = $action->execute($user, $post, 'invalid_type');

        $this->assertEquals('like', $result['type']);
    }

    public function test_handshake_reaction(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $action = new ToggleLike;

        $result = $action->execute($user, $post, 'handshake');

        $this->assertEquals('added', $result['action']);
        $this->assertEquals('handshake', $result['type']);
    }
}
