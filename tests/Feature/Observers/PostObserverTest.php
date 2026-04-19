<?php

namespace Tests\Feature\Observers;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_post_awards_points(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'points' => 0,
        ]);

        Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->assertGreaterThan(0, $member->fresh()->points);
    }

    public function test_deleting_post_deducts_points(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'points' => 100,
        ]);

        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $pointsAfterCreate = $member->fresh()->points;

        $post->delete();
        $this->assertLessThan($pointsAfterCreate, $member->fresh()->points);
    }
}
