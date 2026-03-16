<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── show ───────────────────────────────────────────────────────────────────

    public function test_owner_can_view_leaderboard(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/leaderboard");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Leaderboard')
            ->has('community')
            ->has('leaderboard')
            ->has('leaderboard30')
            ->has('leaderboard7')
            ->has('myPoints')
            ->has('myLevel')
            ->has('levelDistribution')
        );
    }

    public function test_member_can_view_leaderboard_on_free_community(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/leaderboard");

        $response->assertOk();
    }

    public function test_subscriber_can_view_leaderboard_on_paid_community(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/leaderboard");

        $response->assertOk();
    }

    public function test_non_member_cannot_view_leaderboard_on_free_community(): void
    {
        $owner     = User::factory()->create();
        $outsider  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $response = $this->actingAs($outsider)
            ->get("/communities/{$community->slug}/leaderboard");

        $response->assertRedirect("/communities/{$community->slug}/about");
    }

    public function test_non_subscriber_cannot_view_leaderboard_on_paid_community(): void
    {
        $owner     = User::factory()->create();
        $outsider  = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($outsider)
            ->get("/communities/{$community->slug}/leaderboard");

        $response->assertRedirect("/communities/{$community->slug}/about");
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->get("/communities/{$community->slug}/leaderboard");

        $response->assertRedirect('/login');
    }

    public function test_leaderboard_shows_member_points(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'points'       => 100,
        ]);

        $member = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'points'       => 250,
        ]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/leaderboard");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('leaderboard', 2)
        );
    }

    public function test_leaderboard_includes_level_distribution(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'points'       => 0,
        ]);

        CommunityLevelPerk::create([
            'community_id' => $community->id,
            'level'        => 1,
            'description'  => 'Welcome badge',
        ]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/leaderboard");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('levelDistribution')
        );
    }

    public function test_leaderboard_shows_correct_my_points(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'points'       => 75,
        ]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/leaderboard");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('myPoints', 75)
        );
    }
}
