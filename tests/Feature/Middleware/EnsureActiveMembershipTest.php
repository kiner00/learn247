<?php

namespace Tests\Feature\Middleware;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureActiveMembershipTest extends TestCase
{
    use RefreshDatabase;

    // ─── Free community ───────────────────────────────────────────────────────

    public function test_member_of_free_community_can_access(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}/members")
            ->assertOk();
    }

    public function test_non_member_of_free_community_is_denied(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}/members")
            ->assertRedirect("/communities/{$community->slug}");
    }

    public function test_owner_can_always_access(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/members")
            ->assertOk();
    }

    // ─── Paid community ───────────────────────────────────────────────────────

    public function test_active_subscriber_of_paid_community_can_access(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        Subscription::factory()->active()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}/members")
            ->assertOk();
    }

    public function test_user_without_active_subscription_to_paid_community_is_denied(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $this->actingAs($user)
            ->get("/communities/{$community->slug}/members")
            ->assertRedirect("/communities/{$community->slug}");
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $community = Community::factory()->create();

        $this->get("/communities/{$community->slug}/members")
            ->assertRedirect('/login');
    }

    public function test_returns_json_403_for_api_requests(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->getJson("/communities/{$community->slug}/members")
            ->assertForbidden();
    }

    public function test_expired_subscription_is_denied(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();
        Subscription::factory()->expired()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}/members")
            ->assertRedirect("/communities/{$community->slug}");
    }
}
