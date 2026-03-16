<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── me ─────────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_own_profile(): void
    {
        $user = User::factory()->create(['username' => 'johndoe']);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->where('isOwn', true)
            ->where('profileUser.username', 'johndoe')
        );
    }

    public function test_guest_is_redirected_from_me_route(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    public function test_own_profile_shows_crz_token_balance(): void
    {
        $user = User::factory()->create([
            'username'          => 'richuser',
            'crz_token_balance' => 150.50,
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('profileUser.crz_token_balance', 150.50)
        );
    }

    // ─── show ───────────────────────────────────────────────────────────────────

    public function test_public_user_can_view_profile_by_username(): void
    {
        $profileUser = User::factory()->create(['username' => 'janesmith']);

        $response = $this->get('/profile/janesmith');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->where('isOwn', false)
            ->where('profileUser.username', 'janesmith')
        );
    }

    public function test_authenticated_user_viewing_own_profile_via_username(): void
    {
        $user = User::factory()->create(['username' => 'myself']);

        $response = $this->actingAs($user)->get('/profile/myself');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('isOwn', true)
        );
    }

    public function test_nonexistent_username_returns_404(): void
    {
        $response = $this->get('/profile/doesnotexist');

        $response->assertNotFound();
    }

    public function test_profile_hides_crz_balance_for_other_users(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create([
            'username'          => 'targetuser',
            'crz_token_balance' => 999.99,
        ]);

        $response = $this->actingAs($viewer)->get('/profile/targetuser');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('profileUser.crz_token_balance', null)
        );
    }

    public function test_profile_shows_memberships(): void
    {
        $user      = User::factory()->create(['username' => 'memberuser']);
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/profile/memberuser');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('memberships', 1)
        );
    }

    public function test_profile_includes_expected_data(): void
    {
        $user = User::factory()->create([
            'username' => 'fullprofile',
            'bio'      => 'Test bio',
            'location' => 'Manila',
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('totalPoints')
            ->has('myLevel')
            ->has('pointsToNextLevel')
            ->has('activityMap')
            ->has('contributionsCount')
            ->has('badges')
            ->where('profileUser.bio', 'Test bio')
            ->where('profileUser.location', 'Manila')
        );
    }
}
