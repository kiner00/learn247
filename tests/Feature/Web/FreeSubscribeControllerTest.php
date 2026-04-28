<?php

namespace Tests\Feature\Web;

use App\Mail\TempPasswordMail;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FreeSubscribeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_free_subscribe(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->actingAs($user)
            ->post(route('communities.free-subscribe', $community));

        $response->assertRedirect(route('communities.classroom', $community));
        $response->assertSessionHas('success', "You've subscribed for free! Enjoy the courses.");

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);
    }

    public function test_existing_member_is_redirected_without_creating_duplicate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        CommunityMember::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'joined_at' => now(),
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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
        $community = Community::factory()->create();

        CommunityMember::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_PAID,
            'joined_at' => now(),
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
        $user = User::factory()->create();
        $community = Community::factory()->create();

        CommunityMember::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_ADMIN,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'joined_at' => now(),
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

    public function test_guest_can_free_subscribe_and_is_logged_in(): void
    {
        Mail::fake();
        $community = Community::factory()->create();

        $response = $this->post(route('communities.guest.free-subscribe', $community), [
            'first_name' => 'Mark',
            'last_name' => 'Cruz',
            'email' => 'mark@example.com',
            'phone' => '+639171234567',
        ]);

        $response->assertRedirect(route('communities.classroom', $community));
        $response->assertSessionHas('success');

        $user = User::where('email', 'mark@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->needs_password_setup);
        $this->assertEquals($user->id, Auth::id());

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        Mail::assertQueued(TempPasswordMail::class, fn ($mail) => $mail->hasTo('mark@example.com'));
    }

    public function test_guest_free_subscribe_validates_required_fields(): void
    {
        $community = Community::factory()->create();

        $response = $this->post(route('communities.guest.free-subscribe', $community), []);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone']);
    }

    public function test_guest_free_subscribe_reuses_existing_user_without_duplicate_membership(): void
    {
        Mail::fake();
        $existing = User::factory()->create(['email' => 'mark@example.com']);
        $community = Community::factory()->create();

        CommunityMember::create([
            'community_id' => $community->id,
            'user_id' => $existing->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'joined_at' => now(),
        ]);

        $response = $this->post(route('communities.guest.free-subscribe', $community), [
            'first_name' => 'Mark',
            'last_name' => 'Cruz',
            'email' => 'mark@example.com',
            'phone' => '+639171234567',
        ]);

        $response->assertRedirect(route('communities.classroom', $community));
        $this->assertEquals($existing->id, Auth::id());

        $this->assertEquals(
            1,
            CommunityMember::where('community_id', $community->id)
                ->where('user_id', $existing->id)
                ->count()
        );

        Mail::assertNotQueued(TempPasswordMail::class);
    }

    public function test_guest_free_subscribe_blocks_pending_deletion_communities(): void
    {
        $community = Community::factory()->create([
            'deletion_requested_at' => now(),
        ]);

        $response = $this->from('/')->post(route('communities.guest.free-subscribe', $community), [
            'first_name' => 'Mark',
            'last_name' => 'Cruz',
            'email' => 'mark@example.com',
            'phone' => '+639171234567',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'mark@example.com']);
    }
}
