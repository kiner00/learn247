<?php

namespace Tests\Feature\Queries;

use App\Models\Comment;
use App\Models\Community;
use App\Models\Like;
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
        $user = User::factory()->create();
        Post::factory()->count(3)->create(['community_id' => $community->id]);

        $query = new GetCommunityFeed;
        $result = $query->paginated($community, $user->id);

        $this->assertCount(3, $result->items());
    }

    public function test_paginated_enriches_post_reaction_data(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $query = new GetCommunityFeed;
        $result = $query->paginated($community, $user->id);

        $post = $result->items()[0];
        $this->assertTrue(isset($post->reactions));
        $this->assertIsArray($post->reactions);
        $this->assertArrayHasKey('like', $post->reactions);
        $this->assertTrue(isset($post->user_has_liked));
    }

    public function test_for_show_sets_up_feed_data(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        Post::factory()->create(['community_id' => $community->id]);

        $query = new GetCommunityFeed;
        $query->forShow($community, $user->id);

        $this->assertTrue($community->relationLoaded('posts'));
        $this->assertTrue($community->relationLoaded('owner'));
    }

    public function test_for_show_populates_commenter_avatars(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        Comment::factory()->create([
            'post_id' => $post->id,
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $query = new GetCommunityFeed;
        $query->forShow($community, $user->id);

        $enrichedPost = $community->posts->first();
        $this->assertNotNull($enrichedPost->commenter_avatars);
        $this->assertCount(1, $enrichedPost->commenter_avatars);
        $this->assertNotNull($enrichedPost->last_comment_at);
    }

    public function test_paginated_enriches_comment_and_reply_reactions(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $comment = Comment::factory()->create([
            'post_id' => $post->id,
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        Comment::create([
            'post_id' => $post->id,
            'community_id' => $community->id,
            'user_id' => $user->id,
            'parent_id' => $comment->id,
            'content' => 'A reply',
        ]);

        Like::create([
            'user_id' => $user->id,
            'likeable_type' => Comment::class,
            'likeable_id' => $comment->id,
            'type' => 'like',
        ]);

        $query = new GetCommunityFeed;
        $result = $query->paginated($community, $user->id);

        $enrichedPost = $result->items()[0];
        $enrichedComment = $enrichedPost->comments->first();
        $this->assertIsArray($enrichedComment->reactions);
        $this->assertSame(1, $enrichedComment->reactions['like']);
        $this->assertSame('like', $enrichedComment->user_reaction);
        $this->assertTrue($enrichedComment->user_has_liked);
        $this->assertSame(1, $enrichedComment->likes_count);

        $enrichedReply = $enrichedComment->replies->first();
        $this->assertIsArray($enrichedReply->reactions);
        $this->assertFalse($enrichedReply->user_has_liked);
    }
}
