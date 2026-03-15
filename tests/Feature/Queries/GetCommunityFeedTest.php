<?php

namespace Tests\Feature\Queries;

use App\Models\Community;
use App\Models\Post;
use App\Models\User;
use App\Queries\Feed\GetCommunityFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetCommunityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginated_returns_paginated_posts(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        Post::factory()->count(3)->create(['community_id' => $community->id]);

        $query  = new GetCommunityFeed();
        $result = $query->paginated($community, $user->id);

        $this->assertCount(3, $result->items());
    }

    public function test_enrich_post_adds_reaction_data(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $post      = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $post->load([
            'author:id,name,username,avatar',
            'likes',
            'comments' => fn ($q) => $q
                ->whereNull('parent_id')
                ->with([
                    'author:id,name,username,avatar',
                    'likes',
                    'replies' => fn ($r) => $r->with(['author:id,name,username,avatar', 'likes']),
                ])
                ->latest(),
        ])->loadCount('likes', 'comments');

        $query = new GetCommunityFeed();
        $query->enrichPost($post, $user->id);

        $this->assertTrue(isset($post->reactions));
        $this->assertIsArray($post->reactions);
        $this->assertArrayHasKey('like', $post->reactions);
        $this->assertTrue(isset($post->user_has_liked));
    }

    public function test_for_show_sets_up_feed_data(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        Post::factory()->create(['community_id' => $community->id]);

        $query = new GetCommunityFeed();
        $query->forShow($community, $user->id);

        $this->assertTrue($community->relationLoaded('posts'));
        $this->assertTrue($community->relationLoaded('owner'));
    }

}
