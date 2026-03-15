<?php

namespace Tests\Feature\Queries;

use App\Models\Badge;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBadge;
use App\Queries\Profile\GetProfileData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetProfileDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returns_profile_data_for_own_profile(): void
    {
        $user = User::factory()->create();

        $query  = new GetProfileData();
        $result = $query->execute($user, true);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('is_own', $result);
        $this->assertArrayHasKey('memberships', $result);
        $this->assertArrayHasKey('total_points', $result);
        $this->assertArrayHasKey('level', $result);
        $this->assertArrayHasKey('points_to_next', $result);
        $this->assertArrayHasKey('activity_map', $result);
        $this->assertArrayHasKey('badges', $result);
        $this->assertTrue($result['is_own']);
        $this->assertSame($user->id, $result['user']->id);
    }

    public function test_execute_returns_profile_data_for_public_profile(): void
    {
        $user   = User::factory()->create();
        $viewer = User::factory()->create();

        $query  = new GetProfileData();
        $result = $query->execute($user, false);

        $this->assertArrayHasKey('user', $result);
        $this->assertFalse($result['is_own']);
        $this->assertSame($user->id, $result['user']->id);
    }

    public function test_memberships_includes_community_membership(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'     => $user->id,
            'points'      => 10,
        ]);

        $query  = new GetProfileData();
        $result = $query->execute($user, true);

        $this->assertCount(1, $result['memberships']);
        $this->assertSame($community->id, $result['memberships'][0]->community_id);
    }

    public function test_total_points_summed_from_memberships(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'     => $user->id,
            'points'      => 50,
        ]);
        CommunityMember::factory()->create([
            'community_id' => Community::factory()->create()->id,
            'user_id'     => $user->id,
            'points'      => 30,
        ]);

        $query  = new GetProfileData();
        $result = $query->execute($user, true);

        $this->assertSame(80, $result['total_points']);
    }

    public function test_get_level_data_returns_correct_structure(): void
    {
        $user = User::factory()->create();

        $query  = new GetProfileData();
        $result = $query->getLevelData($user);

        $this->assertArrayHasKey('total_points', $result);
        $this->assertArrayHasKey('level', $result);
        $this->assertArrayHasKey('points_to_next', $result);
    }

    public function test_get_contributions_count_returns_zero_without_community(): void
    {
        $user = User::factory()->create();

        $query  = new GetProfileData();
        $result = $query->getContributionsCount($user, null);

        $this->assertSame(0, $result);
    }

    public function test_activity_map_structure(): void
    {
        $user = User::factory()->create();

        $query  = new GetProfileData();
        $result = $query->execute($user, true);

        $this->assertIsArray($result['activity_map']);
    }

    public function test_get_badges_maps_badge_data_for_own_profile(): void
    {
        $user  = User::factory()->create();
        $badge = Badge::create([
            'key'             => 'first_post',
            'type'            => 'member',
            'community_id'    => null,
            'name'            => 'First Post',
            'description'     => 'Created your first post',
            'how_to_earn'     => 'Create a post',
            'icon'            => 'star',
            'condition_type'  => 'post_count',
            'condition_value' => 1,
            'sort_order'      => 1,
        ]);

        UserBadge::create([
            'user_id'   => $user->id,
            'badge_id'  => $badge->id,
            'earned_at' => now(),
        ]);

        $query  = new GetProfileData();
        $result = $query->getBadges($user, true);

        $this->assertNotEmpty($result);
        $this->assertSame('first_post', $result->first()['key']);
        $this->assertTrue($result->first()['earned']);
    }

    public function test_get_badges_returns_only_earned_for_public_profile(): void
    {
        $user  = User::factory()->create();
        $badge = Badge::create([
            'key'             => 'early_bird',
            'type'            => 'member',
            'community_id'    => null,
            'name'            => 'Early Bird',
            'description'     => 'Joined early',
            'how_to_earn'     => 'Sign up early',
            'icon'            => 'clock',
            'condition_type'  => 'signup',
            'condition_value' => 1,
            'sort_order'      => 1,
        ]);

        Badge::create([
            'key'             => 'unearned',
            'type'            => 'member',
            'community_id'    => null,
            'name'            => 'Unearned',
            'description'     => 'Not yet earned',
            'how_to_earn'     => 'Do something',
            'icon'            => 'lock',
            'condition_type'  => 'custom',
            'condition_value' => 1,
            'sort_order'      => 2,
        ]);

        UserBadge::create([
            'user_id'   => $user->id,
            'badge_id'  => $badge->id,
            'earned_at' => now(),
        ]);

        $query  = new GetProfileData();
        $result = $query->getBadges($user, false);

        $this->assertCount(1, $result);
        $this->assertSame('early_bird', $result->first()['key']);
        $this->assertTrue($result->first()['earned']);
    }

    public function test_get_contributions_count_with_community_id(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        Post::factory()->create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
        ]);
        Comment::factory()->create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
        ]);

        $query  = new GetProfileData();
        $result = $query->getContributionsCount($user, $community->id);

        $this->assertSame(2, $result);
    }

    public function test_activity_map_includes_dates_with_posts(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        Post::factory()->create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
            'created_at'   => now(),
        ]);

        $query  = new GetProfileData();
        $result = $query->getActivityMap($user);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey(now()->toDateString(), $result);
        $this->assertGreaterThanOrEqual(1, $result[now()->toDateString()]);
    }

    public function test_activity_map_includes_dates_with_comments(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        Comment::factory()->create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
            'created_at'   => now(),
        ]);

        $query  = new GetProfileData();
        $result = $query->getActivityMap($user);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey(now()->toDateString(), $result);
    }
}
