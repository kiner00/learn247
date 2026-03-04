<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_member_can_create_post(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'community_id' => $community->id,
            'title'        => 'Test Post',
            'content'      => 'Some content here',
        ]);

        $response->assertCreated()->assertJsonPath('data.content', 'Some content here');
        $this->assertDatabaseHas('posts', ['community_id' => $community->id, 'user_id' => $user->id]);
    }

    public function test_unauthenticated_user_cannot_create_post(): void
    {
        $community = Community::factory()->create();

        $this->postJson('/api/posts', ['community_id' => $community->id, 'content' => 'Test'])
            ->assertUnauthorized();
    }

    public function test_create_post_requires_content(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        $this->actingAs($user)->postJson('/api/posts', ['community_id' => $community->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_author_can_delete_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Post deleted.');

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_non_author_cannot_delete_post(): void
    {
        $user     = User::factory()->create();
        $other    = User::factory()->create();
        $community = Community::factory()->create();
        $post     = Post::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $this->actingAs($user)->deleteJson("/api/posts/{$post->id}")
            ->assertForbidden();
    }
}
