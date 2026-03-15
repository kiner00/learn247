<?php

namespace Tests\Feature\Observers;

use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_comment_awards_points(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'points'       => 0,
        ]);
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        Comment::create([
            'post_id'      => $post->id,
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Nice post!',
        ]);

        $this->assertGreaterThan(0, $member->fresh()->points);
    }

    public function test_deleting_comment_deducts_points(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'points'       => 100,
        ]);
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $comment = Comment::create([
            'post_id'      => $post->id,
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Test comment',
        ]);
        $pointsAfterCreate = $member->fresh()->points;

        $comment->delete();
        $this->assertLessThan($pointsAfterCreate, $member->fresh()->points);
    }
}
