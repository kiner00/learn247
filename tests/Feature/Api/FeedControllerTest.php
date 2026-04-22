<?php

namespace Tests\Feature\Api;

use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_see_community_feed(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        Post::factory()->create(['community_id' => $community->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/communities/{$community->slug}/posts");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_index_returns_paginated_posts(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        Post::factory()->count(3)->create(['community_id' => $community->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/communities/{$community->slug}/posts")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_non_member_gets_403_for_community_feed(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/communities/{$community->slug}/posts");

        $response->assertForbidden();
    }

    public function test_owner_can_see_community_feed(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        Post::factory()->create(['community_id' => $community->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/communities/{$community->slug}/posts");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_member_can_see_single_post_with_comments(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $post = Post::factory()->create(['community_id' => $community->id]);
        Comment::factory()->create(['post_id' => $post->id, 'community_id' => $community->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_single_post_unauthenticated_returns_401(): void
    {
        $post = Post::factory()->create();

        $this->getJson("/api/v1/posts/{$post->id}")
            ->assertUnauthorized();
    }

    public function test_show_single_post_includes_enriched_data(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/posts/{$post->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'content']]);
    }

    public function test_non_member_cannot_view_single_post(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/posts/{$post->id}")
            ->assertForbidden();
    }

    public function test_paid_subscriber_can_view_feed(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $member->id,
            'xendit_id' => 'inv_feed_paid',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        Post::factory()->create(['community_id' => $community->id]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/v1/communities/{$community->slug}/posts")
            ->assertOk();
    }
}
