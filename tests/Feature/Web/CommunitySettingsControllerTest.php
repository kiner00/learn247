<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunitySettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithCommunity(): array
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);

        return [$owner, $community];
    }

    // ─── Owner can access settings pages ────────────────────────────────────────

    public function test_owner_can_view_general_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/general");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/General')
            ->has('community')
            ->has('isPro')
            ->has('pricingGate')
        );
    }

    public function test_owner_can_view_affiliate_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/affiliate");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Affiliate')
        );
    }

    public function test_owner_can_view_ai_tools_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/ai-tools");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/AiTools')
        );
    }

    public function test_owner_can_view_domain_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/domain");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Domain')
            ->has('baseDomain')
            ->has('serverIp')
        );
    }

    public function test_owner_can_view_tags_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/tags");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Tags')
            ->has('tags')
        );
    }

    // ─── Non-owner denied ───────────────────────────────────────────────────────

    public function test_regular_member_cannot_view_settings(): void
    {
        $owner   = User::factory()->create();
        $member  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/settings/general");

        $response->assertForbidden();
    }

    public function test_non_member_is_redirected_from_settings(): void
    {
        $owner    = User::factory()->create();
        $outsider = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $response = $this->actingAs($outsider)
            ->get("/communities/{$community->slug}/settings/general");

        // EnsureActiveMembership middleware redirects non-members
        $response->assertRedirect("/communities/{$community->slug}/about");
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->get("/communities/{$community->slug}/settings/general");

        $response->assertRedirect('/login');
    }
}
