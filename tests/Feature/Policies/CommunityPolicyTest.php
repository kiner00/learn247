<?php

namespace Tests\Feature\Policies;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Policies\CommunityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_view_any(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->can('viewAny', Community::class));
    }

    public function test_anyone_can_view(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->assertTrue($user->can('view', $community));
    }

    public function test_guest_can_view(): void
    {
        $community = Community::factory()->create();
        $this->assertTrue((new CommunityPolicy)->view(null, $community));
    }

    public function test_any_user_can_create(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->can('create', Community::class));
    }

    public function test_owner_can_update(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $this->assertTrue($owner->can('update', $community));
    }

    public function test_non_owner_cannot_update(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->assertFalse($user->can('update', $community));
    }

    public function test_owner_can_delete(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $this->assertTrue($owner->can('delete', $community));
    }

    public function test_non_owner_cannot_delete(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $this->assertFalse($user->can('delete', $community));
    }

    public function test_owner_can_view_analytics(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $this->assertTrue($owner->can('viewAnalytics', $community));
    }

    public function test_admin_can_view_analytics(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($user->can('viewAnalytics', $community));
    }

    public function test_regular_member_cannot_view_analytics(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertFalse($user->can('viewAnalytics', $community));
    }

    public function test_admin_can_manage_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($user->can('manageMember', $community));
    }

    public function test_moderator_can_manage_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->moderator()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($user->can('manageMember', $community));
    }

    public function test_regular_member_cannot_manage_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertFalse($user->can('manageMember', $community));
    }
}
