<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\ChangeMemberRole;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeMemberRoleTest extends TestCase
{
    use RefreshDatabase;

    private ChangeMemberRole $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ChangeMemberRole;
    }

    public function test_owner_can_promote_member_to_moderator(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id, 'role' => CommunityMember::ROLE_MEMBER]);

        $updated = $this->action->execute($owner, $community, $member, CommunityMember::ROLE_MODERATOR);

        $this->assertEquals(CommunityMember::ROLE_MODERATOR, $updated->role);
    }

    public function test_throws_for_invalid_role(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->action->execute($owner, $community, $member, 'superadmin');
    }

    public function test_non_owner_cannot_change_roles(): void
    {
        $community = Community::factory()->create();
        $actor = User::factory()->create();
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $actor->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $this->expectException(AuthorizationException::class);
        $this->action->execute($actor, $community, $member, CommunityMember::ROLE_MODERATOR);
    }

    public function test_cannot_demote_owner(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $this->expectException(AuthorizationException::class);
        $this->action->execute($owner, $community, $owner, CommunityMember::ROLE_MEMBER);
    }
}
