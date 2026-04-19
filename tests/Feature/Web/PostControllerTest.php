<?php

namespace Tests\Feature\Web;

use App\Actions\Feed\CreatePost;
use App\Actions\Feed\DeletePost;
use App\Actions\Feed\TogglePin;
use App\Actions\Feed\UpdatePost;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_member_can_create_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/posts", [
                'content' => 'Hello community!',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'community_id' => $community->id,
            'user_id' => $member->id,
            'content' => 'Hello community!',
        ]);
    }

    public function test_store_requires_content(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/posts", [
                'content' => '',
            ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_store_with_title_and_content(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/posts", [
                'title' => 'My First Post',
                'content' => 'With a title!',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'community_id' => $community->id,
            'title' => 'My First Post',
            'content' => 'With a title!',
        ]);
    }

    public function test_guest_cannot_create_post(): void
    {
        $community = Community::factory()->create();

        $response = $this->post("/communities/{$community->slug}/posts", [
            'content' => 'Should not work',
        ]);

        $response->assertRedirect('/login');
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_author_can_delete_own_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $response = $this->actingAs($member)
            ->delete("/posts/{$post->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_admin_member_can_delete_others_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $admin = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $admin->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $response = $this->actingAs($admin)
            ->delete("/posts/{$post->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_regular_member_cannot_delete_others_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $author = User::factory()->create();
        $other = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $author->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $author->id,
        ]);

        $response = $this->actingAs($other)
            ->delete("/posts/{$post->id}");

        $response->assertForbidden();
    }

    // ─── togglePin ────────────────────────────────────────────────────────────

    public function test_owner_can_pin_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'is_pinned' => false,
        ]);

        $response = $this->actingAs($owner)
            ->post("/posts/{$post->id}/pin");

        $response->assertRedirect();
        $this->assertTrue($post->fresh()->is_pinned);
    }

    public function test_owner_can_unpin_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'is_pinned' => true,
        ]);

        $response = $this->actingAs($owner)
            ->post("/posts/{$post->id}/pin");

        $response->assertRedirect();
        $this->assertFalse($post->fresh()->is_pinned);
    }

    public function test_admin_member_can_pin_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $admin->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'is_pinned' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post("/posts/{$post->id}/pin");

        $response->assertRedirect();
        $this->assertTrue($post->fresh()->is_pinned);
    }

    public function test_regular_member_cannot_pin_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'is_pinned' => false,
        ]);

        $response = $this->actingAs($member)
            ->post("/posts/{$post->id}/pin");

        $response->assertForbidden();
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_author_can_update_post(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)->patch("/posts/{$post->id}", [
            'content' => 'Updated content here.',
        ])->assertRedirect();

        $this->assertEquals('Updated content here.', $post->fresh()->content);
    }

    public function test_non_author_cannot_update_post(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $author->id]);

        $this->actingAs($other)->patch("/posts/{$post->id}", [
            'content' => 'Hacked content.',
        ])->assertForbidden();
    }

    public function test_update_requires_content(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)->patch("/posts/{$post->id}", [
            'content' => '',
        ])->assertSessionHasErrors('content');
    }

    // ─── additional store tests ──────────────────────────────────────────────

    public function test_store_fails_with_content_too_long(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/posts", [
                'content' => str_repeat('x', 10001),
            ]);

        $response->assertSessionHasErrors('content');
    }

    // ─── additional update tests ─────────────────────────────────────────────

    public function test_author_can_update_post_title(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'title' => 'Old Title',
        ]);

        $this->actingAs($user)->patch("/posts/{$post->id}", [
            'title' => 'New Title',
            'content' => 'Updated content.',
        ])->assertRedirect();

        $this->assertEquals('New Title', $post->fresh()->title);
    }

    public function test_guest_cannot_update_post(): void
    {
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);

        $this->patch("/posts/{$post->id}", [
            'content' => 'Hacked',
        ])->assertRedirect('/login');
    }

    // ─── additional destroy tests ────────────────────────────────────────────

    public function test_guest_cannot_delete_post(): void
    {
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);

        $this->delete("/posts/{$post->id}")->assertRedirect('/login');
    }

    public function test_owner_can_delete_any_post_in_community(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        // Owner is not in community_members but owns the community.
        // The DeletePost action checks canModerate or is the author.
        // Owner needs a membership record to pass the action check.
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->delete("/posts/{$post->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    // ─── additional togglePin tests ──────────────────────────────────────────

    public function test_guest_cannot_pin_post(): void
    {
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);

        $this->post("/posts/{$post->id}/pin")->assertRedirect('/login');
    }

    public function test_moderator_cannot_pin_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $moderator = User::factory()->create();
        CommunityMember::factory()->moderator()->create([
            'community_id' => $community->id,
            'user_id' => $moderator->id,
        ]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'is_pinned' => false,
        ]);

        $this->actingAs($moderator)
            ->post("/posts/{$post->id}/pin")
            ->assertForbidden();
    }

    // ─── error branch: store ─────────────────────────────────────────────────

    public function test_store_returns_error_session_when_action_throws(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $mock = Mockery::mock(CreatePost::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('disk full'));
        $this->app->instance(CreatePost::class, $mock);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/posts", ['content' => 'Hello!']);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to create post.');
    }

    // ─── error branch: update ────────────────────────────────────────────────

    public function test_update_returns_error_session_when_action_throws(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $mock = Mockery::mock(UpdatePost::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(UpdatePost::class, $mock);

        $response = $this->actingAs($user)
            ->patch("/posts/{$post->id}", ['content' => 'Updated content.']);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to update post.');
    }

    // ─── error branch: destroy ───────────────────────────────────────────────

    public function test_destroy_returns_error_session_when_action_throws(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $mock = Mockery::mock(DeletePost::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(DeletePost::class, $mock);

        $response = $this->actingAs($user)
            ->delete("/posts/{$post->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to delete post.');
    }

    // ─── error branch: togglePin ─────────────────────────────────────────────

    public function test_toggle_pin_returns_error_session_when_action_throws(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $post = Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'is_pinned' => false,
        ]);

        $mock = Mockery::mock(TogglePin::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(TogglePin::class, $mock);

        $response = $this->actingAs($owner)
            ->post("/posts/{$post->id}/pin");

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to toggle pin.');
    }
}
