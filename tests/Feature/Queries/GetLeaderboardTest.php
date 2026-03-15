<?php

namespace Tests\Feature\Queries;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use App\Queries\Community\GetLeaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returns_leaderboard_data(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'points'       => 150,
        ]);

        $query = new GetLeaderboard();
        $result = $query->execute($community, $user->id);

        $this->assertEquals(150, $result['my_points']);
        $this->assertArrayHasKey('my_level', $result);
        $this->assertArrayHasKey('leaderboard', $result);
        $this->assertArrayHasKey('leaderboard_30_days', $result);
        $this->assertArrayHasKey('leaderboard_7_days', $result);
        $this->assertArrayHasKey('level_perks', $result);
    }

    public function test_execute_for_non_member_has_zero_points(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $query = new GetLeaderboard();
        $result = $query->execute($community, $user->id);

        $this->assertEquals(0, $result['my_points']);
    }

    public function test_top_members_returns_ordered_by_points(): void
    {
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'points' => 100]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'points' => 300]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'points' => 200]);

        $query = new GetLeaderboard();
        $result = $query->topMembers($community);

        $this->assertCount(3, $result);
        $this->assertEquals(300, $result[0]['points']);
        $this->assertEquals(200, $result[1]['points']);
        $this->assertEquals(100, $result[2]['points']);
    }

    public function test_period_leaderboard_includes_recent_activity(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'created_at'   => now()->subDays(3),
        ]);

        $query = new GetLeaderboard();
        $result = $query->execute($community, $user->id);

        $this->assertNotEmpty($result['leaderboard_7_days']);
    }

    public function test_period_leaderboard_excludes_old_activity(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        Post::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'created_at'   => now()->subDays(40),
        ]);

        $query = new GetLeaderboard();
        $result = $query->execute($community, $user->id);

        $this->assertEmpty($result['leaderboard_30_days']);
    }
}
