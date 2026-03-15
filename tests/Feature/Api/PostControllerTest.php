<?php

namespace Tests\Feature\Api;

use App\Actions\Feed\TogglePin;
use App\Http\Controllers\Api\PostController;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_with_community_slug_creates_post(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'community_slug' => $community->slug,
            'content'        => 'Post content via slug',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'Post content via slug');
        $this->assertDatabaseHas('posts', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Post content via slug',
        ]);
    }

    public function test_store_with_community_id_creates_post(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'community_id' => $community->id,
            'content'      => 'Post content via id',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'Post content via id');
        $this->assertDatabaseHas('posts', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Post content via id',
        ]);
    }

    public function test_store_validates_content_required(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/posts', [
            'community_id' => $community->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_store_requires_community_reference(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)->postJson('/api/posts', [
            'content' => 'Content without community',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['community_id', 'community_slug']);
    }

    public function test_author_can_destroy_own_post(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $post      = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $this->actingAs($user)->deleteJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Post deleted.');

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_non_author_cannot_destroy_post(): void
    {
        $user      = User::factory()->create();
        $author    = User::factory()->create();
        $community = Community::factory()->create();
        $post      = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $author->id,
        ]);

        $this->actingAs($user)->deleteJson("/api/posts/{$post->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_unauthenticated_cannot_create_post(): void
    {
        $community = Community::factory()->create();

        $this->postJson('/api/posts', [
            'community_id' => $community->id,
            'content'      => 'Some content',
        ])
            ->assertUnauthorized();
    }

    public function test_admin_can_pin_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'role'         => 'admin',
        ]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'is_pinned'    => false,
        ]);

        $this->actingAs($owner, 'sanctum');

        $controller = app(PostController::class);
        $response   = $controller->togglePin($post, app(TogglePin::class));
        $data       = json_decode($response->getContent(), true);

        $this->assertEquals('Post pinned.', $data['message']);
        $this->assertTrue($data['is_pinned']);
        $this->assertTrue($post->refresh()->is_pinned);
    }

    public function test_admin_can_unpin_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'role'         => 'admin',
        ]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'is_pinned'    => true,
        ]);

        $this->actingAs($owner, 'sanctum');

        $controller = app(PostController::class);
        $response   = $controller->togglePin($post, app(TogglePin::class));
        $data       = json_decode($response->getContent(), true);

        $this->assertEquals('Post unpinned.', $data['message']);
        $this->assertFalse($data['is_pinned']);
        $this->assertFalse($post->refresh()->is_pinned);
    }

    public function test_non_admin_cannot_toggle_pin(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'is_pinned'    => false,
        ]);

        $this->actingAs($user, 'sanctum');

        $this->expectException(AuthorizationException::class);

        $controller = app(PostController::class);
        $controller->togglePin($post, app(TogglePin::class));
    }
}
