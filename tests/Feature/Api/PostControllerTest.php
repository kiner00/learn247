<?php

namespace Tests\Feature\Api;

use App\Actions\Feed\CreatePost;
use App\Actions\Feed\DeletePost;
use App\Actions\Feed\TogglePin;
use App\Actions\Feed\UpdatePost;
use App\Http\Controllers\Api\PostController;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
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

    // ─── update (controller-level, no route) ─────────────────────────────────

    public function test_author_can_update_post_via_controller(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $post      = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Original content',
        ]);

        $this->actingAs($user, 'sanctum');

        $request = UpdatePostRequest::create("/api/posts/{$post->id}", 'PATCH', [
            'content' => 'Updated API content',
        ]);
        $request->setUserResolver(fn () => $user);
        $request->setContainer(app());
        // Manually trigger validation so validated() returns data
        $request->validateResolved();

        $controller = app(PostController::class);
        $response   = $controller->update($request, $post, app(UpdatePost::class));
        $data       = json_decode($response->getContent(), true);

        $this->assertEquals('Post updated.', $data['message']);
        $this->assertEquals('Updated API content', $post->fresh()->content);
    }

    public function test_update_returns_500_when_action_throws(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $post      = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $this->actingAs($user, 'sanctum');

        $mock = Mockery::mock(UpdatePost::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('db error'));

        $request = UpdatePostRequest::create("/api/posts/{$post->id}", 'PATCH', [
            'content' => 'Updated content',
        ]);
        $request->setUserResolver(fn () => $user);
        $request->setContainer(app());
        $request->validateResolved();

        $controller = app(PostController::class);
        $response   = $controller->update($request, $post, $mock);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Failed to update post.', $data['message']);
    }

    // ─── error branch: destroy ───────────────────────────────────────────────

    public function test_destroy_returns_500_when_action_throws(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $post      = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $mock = Mockery::mock(DeletePost::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(DeletePost::class, $mock);

        $this->actingAs($user)
            ->deleteJson("/api/posts/{$post->id}")
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to delete post.');
    }

    // ─── error branch: togglePin ─────────────────────────────────────────────

    public function test_toggle_pin_returns_500_when_action_throws_runtime(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'is_pinned'    => false,
        ]);

        $this->actingAs($owner, 'sanctum');

        $mock = Mockery::mock(TogglePin::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('db error'));

        $controller = app(PostController::class);
        $response   = $controller->togglePin($post, $mock);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Failed to toggle pin.', $data['message']);
    }

    // ─── store: with image ──────────────────────────────────────────────────

    public function test_store_with_image_creates_post(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('filesystems.default'));

        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $file = \Illuminate\Http\UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'community_id' => $community->id,
            'content'      => 'Post with image',
            'image'        => $file,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Post with image',
        ]);
    }

    // ─── unauthenticated destroy ─────────────────────────────────────────────

    public function test_unauthenticated_cannot_destroy_post(): void
    {
        $community = Community::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id]);

        $this->deleteJson("/api/posts/{$post->id}")
            ->assertUnauthorized();
    }
}
