<?php

namespace Tests\Feature\Web;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── redirect ─────────────────────────────────────────────────────────────

    public function test_valid_code_redirects_to_community_and_sets_cookie(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'code' => 'TESTREF123',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $response = $this->get('/ref/TESTREF123');

        $response->assertRedirect(route('communities.about', $community->slug).'?modal=true');
        $response->assertCookie('ref_code', 'TESTREF123');
    }

    public function test_inactive_affiliate_code_redirects_to_communities_index(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'code' => 'INACTIVE01',
            'status' => Affiliate::STATUS_INACTIVE,
        ]);

        $response = $this->get('/ref/INACTIVE01');

        $response->assertRedirect(route('communities.index'));
    }

    public function test_nonexistent_code_redirects_to_communities_index(): void
    {
        $response = $this->get('/ref/DOESNOTEXIST');

        $response->assertRedirect(route('communities.index'));
    }

    public function test_ref_route_is_accessible_without_auth(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'code' => 'PUBLIC01',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $response = $this->get('/ref/PUBLIC01');

        $response->assertRedirect(route('communities.about', $community->slug).'?modal=true');
    }

    public function test_creator_plan_affiliate_code_redirects_to_creator_plan_page(): void
    {
        $user = User::factory()->create();
        Affiliate::create([
            'user_id' => $user->id,
            'community_id' => null,
            'scope' => Affiliate::SCOPE_CREATOR_PLAN,
            'code' => 'CPAFF001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $response = $this->get('/ref/CPAFF001');

        $response->assertRedirect('/creator/plan');
        $response->assertCookie('ref_code', 'CPAFF001');
    }
}
