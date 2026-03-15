<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureActiveMembership;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
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
            ->assertRedirect("/communities/{$community->slug}/about");
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
            ->assertRedirect("/communities/{$community->slug}/about");
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
            ->assertRedirect("/communities/{$community->slug}/about");
    }

    public function test_unauthenticated_json_request_returns_401(): void
    {
        $community = Community::factory()->create();

        $this->getJson("/communities/{$community->slug}/members")
            ->assertStatus(401);
    }

    public function test_non_subscriber_paid_community_json_returns_403(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $this->actingAs($user)
            ->getJson("/communities/{$community->slug}/members")
            ->assertForbidden()
            ->assertJsonPath('message', 'An active membership is required.');
    }

    public function test_resolves_community_from_slug_route_parameter(): void
    {
        Route::get('/test-slug/{slug}', fn () => response('OK'))
            ->middleware(['web', 'auth', EnsureActiveMembership::class]);

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->get("/test-slug/{$community->slug}")
            ->assertOk();
    }

    public function test_resolves_community_from_non_model_bound_community_param(): void
    {
        Route::get('/test-raw/{community}', fn () => response('OK'))
            ->middleware(['web', 'auth', EnsureActiveMembership::class])
            ->withoutMiddleware(\Illuminate\Routing\Middleware\SubstituteBindings::class);

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->get("/test-raw/{$community->slug}")
            ->assertOk();
    }

    public function test_invalid_slug_returns_404(): void
    {
        Route::get('/test-slug/{slug}', fn () => response('OK'))
            ->middleware(['web', 'auth', EnsureActiveMembership::class]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/test-slug/non-existent-community')
            ->assertNotFound();
    }

    public function test_null_community_and_slug_returns_404(): void
    {
        Route::get('/test-no-params', fn () => response('OK'))
            ->middleware(['web', 'auth', EnsureActiveMembership::class]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/test-no-params')
            ->assertNotFound();
    }

    public function test_slug_param_member_of_free_community_passes(): void
    {
        Route::get('/test-slug/{slug}', fn () => response('OK'))
            ->middleware(['web', 'auth', EnsureActiveMembership::class]);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/test-slug/{$community->slug}")
            ->assertOk();
    }

    public function test_slug_param_non_member_free_community_denied(): void
    {
        Route::get('/test-slug/{slug}', fn () => response('OK'))
            ->middleware(['web', 'auth', EnsureActiveMembership::class]);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->get("/test-slug/{$community->slug}")
            ->assertRedirect("/communities/{$community->slug}/about");
    }

    public function test_slug_param_paid_community_non_subscriber_denied(): void
    {
        Route::get('/test-slug/{slug}', fn () => response('OK'))
            ->middleware(['web', 'auth', EnsureActiveMembership::class]);

        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $this->actingAs($user)
            ->get("/test-slug/{$community->slug}")
            ->assertRedirect("/communities/{$community->slug}/about");
    }

    public function test_slug_param_paid_community_json_non_subscriber_denied(): void
    {
        Route::get('/test-slug/{slug}', fn () => response('OK'))
            ->middleware(['web', 'auth', EnsureActiveMembership::class]);

        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $this->actingAs($user)
            ->getJson("/test-slug/{$community->slug}")
            ->assertForbidden()
            ->assertJsonPath('message', 'An active membership is required.');
    }

    public function test_slug_param_free_community_json_non_member_denied(): void
    {
        Route::get('/test-slug/{slug}', fn () => response('OK'))
            ->middleware(['web', 'auth', EnsureActiveMembership::class]);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->getJson("/test-slug/{$community->slug}")
            ->assertForbidden()
            ->assertJsonPath('message', 'You must be a member of this community.');
    }

    public function test_unauthenticated_slug_param_redirects_to_login(): void
    {
        Route::get('/test-slug/{slug}', fn () => response('OK'))
            ->middleware(['web', EnsureActiveMembership::class]);

        $community = Community::factory()->create();

        $this->get("/test-slug/{$community->slug}")
            ->assertRedirect('/login');
    }

    public function test_unauthenticated_slug_param_json_returns_401(): void
    {
        Route::get('/test-slug/{slug}', fn () => response('OK'))
            ->middleware(['web', EnsureActiveMembership::class]);

        $community = Community::factory()->create();

        $this->getJson("/test-slug/{$community->slug}")
            ->assertStatus(401);
    }
}
