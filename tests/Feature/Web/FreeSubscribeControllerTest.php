<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeSubscribeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_free_subscribe(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->actingAs($user)
            ->post(route('communities.free-subscribe', $community));

        $response->assertRedirect(route('communities.classroom', $community));
        $response->assertSessionHas('success', "You've subscribed for free! Enjoy the courses.");

        $this->assertDatabaseHas('community_members', [
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'role'            => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);
    }

    public function test_existing_member_is_redirected_without_creating_duplicate(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        CommunityMember::create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'role'            => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'joined_at'       => now(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('communities.free-subscribe', $community));

        $response->assertRedirect(route('communities.classroom', $community));
        $response->assertSessionMissing('success');

        $this->assertEquals(
            1,
            CommunityMember::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->count()
        );
    }

    public function test_guest_cannot_free_subscribe(): void
    {
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->post(route('communities.free-subscribe', $community));

        $response->assertRedirect('/login');
    }

    public function test_creates_member_with_correct_joined_at_timestamp(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->post(route('communities.free-subscribe', $community));

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertNotNull($member->joined_at);
        // joined_at should be within the last minute
        $this->assertTrue($member->joined_at->diffInSeconds(now()) < 60);
    }

    public function test_existing_paid_member_is_not_duplicated(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        CommunityMember::create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'role'            => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_PAID,
            'joined_at'       => now(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('communities.free-subscribe', $community));

        $response->assertRedirect(route('communities.classroom', $community));

        $this->assertEquals(
            1,
            CommunityMember::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->count()
        );
    }

    public function test_admin_member_is_not_duplicated(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        CommunityMember::create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'role'            => CommunityMember::ROLE_ADMIN,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'joined_at'       => now(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('communities.free-subscribe', $community));

        $response->assertRedirect(route('communities.classroom', $community));

        $this->assertEquals(
            1,
            CommunityMember::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->count()
        );
    }
}
