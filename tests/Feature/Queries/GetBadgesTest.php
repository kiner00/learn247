<?php

namespace Tests\Feature\Queries;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Queries\Badge\GetBadges;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_member_and_creator_badges(): void
    {
        BadgeService::seedDefaults();

        $query = new GetBadges();
        $result = $query->execute();

        $this->assertArrayHasKey('member', $result);
        $this->assertArrayHasKey('creator', $result);
        $this->assertGreaterThan(0, $result['member']->count());
        $this->assertGreaterThan(0, $result['creator']->count());
    }

    public function test_marks_earned_badges_for_user(): void
    {
        BadgeService::seedDefaults();
        $user = User::factory()->create();
        $badge = Badge::where('key', 'pioneer_member')->first();

        UserBadge::create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'community_id' => null,
            'earned_at' => now(),
        ]);

        $query = new GetBadges();
        $result = $query->execute($user->id);

        $pioneer = $result['member']->firstWhere('key', 'pioneer_member');
        $this->assertTrue($pioneer['earned']);
    }

    public function test_unearned_badges_marked_false(): void
    {
        BadgeService::seedDefaults();
        $user = User::factory()->create();

        $query = new GetBadges();
        $result = $query->execute($user->id);

        $pioneer = $result['member']->firstWhere('key', 'pioneer_member');
        $this->assertFalse($pioneer['earned']);
    }

    public function test_without_user_all_badges_unearned(): void
    {
        BadgeService::seedDefaults();

        $query = new GetBadges();
        $result = $query->execute(null);

        $allBadges = $result['member']->merge($result['creator']);
        $this->assertTrue($allBadges->every(fn ($b) => $b['earned'] === false));
    }
}
