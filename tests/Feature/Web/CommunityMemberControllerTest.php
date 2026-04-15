<?php

namespace Tests\Feature\Web;

use App\Actions\Community\ChangeMemberRole;
use App\Actions\Community\ExtendMemberAccess;
use App\Actions\Community\RemoveMember;
use App\Actions\Community\ToggleMemberBlock;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_remove_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
            'role'         => CommunityMember::ROLE_ADMIN,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($admin)
            ->delete(route('communities.members.destroy', [$community, $member]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Member removed.');

        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);
    }

    public function test_regular_member_cannot_remove_another_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $actor = User::factory()->create();
        $target = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $actor->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $target->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($actor)
            ->delete(route('communities.members.destroy', [$community, $target]))
            ->assertForbidden();
    }

    public function test_cannot_remove_community_owner(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
            'role'         => CommunityMember::ROLE_ADMIN,
        ]);

        $this->actingAs($admin)
            ->delete(route('communities.members.destroy', [$community, $owner]))
            ->assertForbidden();
    }

    public function test_guest_cannot_remove_member(): void
    {
        $community = Community::factory()->create();
        $member = User::factory()->create();

        $this->delete(route('communities.members.destroy', [$community, $member]))
            ->assertRedirect('/login');
    }

    // ── changeRole ────────────────────────────────────────────────────────────

    public function test_owner_can_change_member_role(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.role', [$community, $member]), ['role' => 'admin'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_ADMIN,
        ]);
    }

    public function test_non_owner_cannot_change_role(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
            'role'         => CommunityMember::ROLE_ADMIN,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($admin)
            ->patch(route('communities.members.role', [$community, $member]), ['role' => 'moderator'])
            ->assertForbidden();
    }

    public function test_change_role_validates_role_field(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.role', [$community, $member]), ['role' => 'superadmin'])
            ->assertSessionHasErrors('role');
    }

    public function test_change_role_requires_role_field(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.role', [$community, $member]), [])
            ->assertSessionHasErrors('role');
    }

    // ── toggleBlock ──────────────────────────────────────────────────────────

    public function test_owner_can_block_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
            'is_blocked'   => false,
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.block', [$community, $member]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'is_blocked'   => true,
        ]);
    }

    public function test_owner_can_unblock_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
            'is_blocked'   => true,
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.block', [$community, $member]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'is_blocked'   => false,
        ]);
    }

    public function test_admin_can_toggle_block(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();
        $member = User::factory()->create();

        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'is_blocked'   => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('communities.members.block', [$community, $member]))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_regular_member_cannot_toggle_block(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $actor = User::factory()->create();
        $target = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $actor->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $target->id,
        ]);

        $this->actingAs($actor)
            ->patch(route('communities.members.block', [$community, $target]))
            ->assertForbidden();
    }

    public function test_cannot_block_community_owner(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();

        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('communities.members.block', [$community, $owner]))
            ->assertForbidden();
    }

    // ── extendAccess ─────────────────────────────────────────────────────────

    public function test_owner_can_extend_member_access(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id'  => $community->id,
            'user_id'       => $member->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at'    => now()->subDay(),
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.extend-access', $community), [
                'user_ids' => [$member->id],
                'months'   => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_non_owner_cannot_extend_access(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();
        $member = User::factory()->create();

        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
        ]);
        CommunityMember::factory()->create([
            'community_id'  => $community->id,
            'user_id'       => $member->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $this->actingAs($admin)
            ->patch(route('communities.members.extend-access', $community), [
                'user_ids' => [$member->id],
                'months'   => 3,
            ])
            ->assertForbidden();
    }

    public function test_extend_access_validates_required_fields(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('communities.members.extend-access', $community), [])
            ->assertSessionHasErrors(['user_ids', 'months']);
    }

    public function test_extend_access_validates_months_min(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('communities.members.extend-access', $community), [
                'user_ids' => [1],
                'months'   => 0,
            ])
            ->assertSessionHasErrors('months');
    }

    public function test_extend_access_validates_months_max(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('communities.members.extend-access', $community), [
                'user_ids' => [1],
                'months'   => 121,
            ])
            ->assertSessionHasErrors('months');
    }

    public function test_extend_access_singular_message_for_one_member_one_month(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $member->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at'      => now()->subDay(),
        ]);

        $response = $this->actingAs($owner)
            ->patch(route('communities.members.extend-access', $community), [
                'user_ids' => [$member->id],
                'months'   => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Extended access for 1 member by 1 month.');
    }

    public function test_moderator_can_remove_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $moderator = User::factory()->create();
        $member = User::factory()->create();

        CommunityMember::factory()->moderator()->create([
            'community_id' => $community->id,
            'user_id'      => $moderator->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $this->actingAs($moderator)
            ->delete(route('communities.members.destroy', [$community, $member]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Member removed.');
    }

    // ── destroy error catch path ─────────────────────────────────────────────

    public function test_destroy_catches_generic_exception_and_returns_error(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        // Mock the action to throw a generic exception (not AuthorizationException)
        $this->mock(RemoveMember::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new \RuntimeException('DB connection lost'));
        });

        $this->actingAs($owner)
            ->delete(route('communities.members.destroy', [$community, $member]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Failed to remove member.');
    }

    // ── toggleBlock error catch path ─────────────────────────────────────────

    public function test_toggle_block_catches_generic_exception_and_returns_error(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'is_blocked'   => false,
        ]);

        $this->mock(ToggleMemberBlock::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new \RuntimeException('Unexpected error'));
        });

        $this->actingAs($owner)
            ->patch(route('communities.members.block', [$community, $member]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Failed to toggle block status.');
    }

    // ── setExpiry ────────────────────────────────────────────────────────────

    public function test_owner_can_set_member_expiry(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $member->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at'      => null,
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.set-expiry', $community), [
                'user_ids' => [$member->id],
                'months'   => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull(CommunityMember::where('user_id', $member->id)->first()->expires_at);
    }

    public function test_set_expiry_overwrites_existing_expiry(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $member->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at'      => now()->addYears(5),
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.set-expiry', $community), [
                'user_ids' => [$member->id],
                'months'   => 1,
            ])
            ->assertSessionHas('success');

        $newExpiry = CommunityMember::where('user_id', $member->id)->first()->expires_at;
        $this->assertTrue($newExpiry->lessThan(now()->addMonths(2)));
    }

    public function test_set_expiry_accepts_null_for_no_expiry(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $member->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at'      => now()->addMonth(),
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.members.set-expiry', $community), [
                'user_ids' => [$member->id],
                'months'   => null,
            ])
            ->assertSessionHas('success');

        $this->assertNull(CommunityMember::where('user_id', $member->id)->first()->expires_at);
    }

    public function test_non_owner_cannot_set_expiry(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin = User::factory()->create();

        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('communities.members.set-expiry', $community), [
                'user_ids' => [$admin->id],
                'months'   => 1,
            ])
            ->assertForbidden();
    }

    // ── extendAccess error catch path ────────────────────────────────────────

    public function test_extend_access_catches_generic_exception_and_returns_error(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $member->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $this->mock(ExtendMemberAccess::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new \RuntimeException('DB timeout'));
        });

        $this->actingAs($owner)
            ->patch(route('communities.members.extend-access', $community), [
                'user_ids' => [$member->id],
                'months'   => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Failed to extend access.');
    }

    // ── changeRole error catch path ──────────────────────────────────────────

    public function test_change_role_catches_generic_exception_and_returns_error(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);

        $this->mock(ChangeMemberRole::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new \RuntimeException('Permission check failed'));
        });

        $this->actingAs($owner)
            ->patch(route('communities.members.role', [$community, $member]), ['role' => 'admin'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Failed to change role.');
    }

    // ── extendAccess plural message ──────────────────────────────────────────

    public function test_extend_access_plural_message_for_multiple_members(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $member1->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at'      => now()->subDay(),
        ]);
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $member2->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at'      => now()->subDay(),
        ]);

        $response = $this->actingAs($owner)
            ->patch(route('communities.members.extend-access', $community), [
                'user_ids' => [$member1->id, $member2->id],
                'months'   => 3,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Extended access for 2 members by 3 months.');
    }
}
