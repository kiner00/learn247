<?php

namespace Tests\Feature\Services;

use App\Models\Badge;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CourseLesson;
use App\Models\LessonCompletion;
use App\Models\Post;
use App\Models\User;
use App\Services\Badge\BadgeConditionChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgeConditionCheckerTest extends TestCase
{
    use RefreshDatabase;

    private BadgeConditionChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new BadgeConditionChecker();
    }

    public function test_posts_created_condition_met(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        Post::factory()->count(5)->create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
        ]);

        $badge = Badge::create([
            'name'            => 'Prolific Poster',
            'key'             => 'prolific_poster',
            'icon'            => 'trophy',
            'condition_type'  => 'posts_created',
            'condition_value' => 5,
        ]);

        $this->assertTrue($this->checker->conditionMet($user, $badge, $community->id));
    }

    public function test_posts_created_condition_not_met(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        Post::factory()->count(2)->create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
        ]);

        $badge = Badge::create([
            'name'            => 'Prolific Poster',
            'key'             => 'prolific_poster_2',
            'icon'            => 'trophy',
            'condition_type'  => 'posts_created',
            'condition_value' => 5,
        ]);

        $this->assertFalse($this->checker->conditionMet($user, $badge, $community->id));
    }

    public function test_pioneer_member_condition_met_for_early_user(): void
    {
        $user = User::factory()->create();

        $badge = Badge::create([
            'name'            => 'Pioneer Member',
            'key'             => 'pioneer_member',
            'icon'            => 'star',
            'condition_type'  => 'pioneer_member',
            'condition_value' => 0,
        ]);

        // First user in the system should qualify
        $this->assertTrue($this->checker->conditionMet($user, $badge, null));
    }

    public function test_unknown_condition_type_returns_false(): void
    {
        $user = User::factory()->create();

        $badge = Badge::create([
            'name'            => 'Unknown',
            'key'             => 'unknown_badge',
            'icon'            => 'question',
            'condition_type'  => 'nonexistent_condition',
            'condition_value' => 1,
        ]);

        $this->assertFalse($this->checker->conditionMet($user, $badge, null));
    }
}
