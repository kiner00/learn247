<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\RemoveMember;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveMemberTest extends TestCase
{
    use RefreshDatabase;

    private RemoveMember $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RemoveMember();
    }

    public function test_admin_can_remove_a_member(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $admin     = CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $this->action->execute($owner, $community, $member);

        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);
    }

    public function test_cannot_remove_community_owner(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $this->expectException(AuthorizationException::class);
        $this->action->execute($owner, $community, $owner);
    }

    public function test_non_moderator_cannot_remove_member(): void
    {
        $community = Community::factory()->create();
        $actor     = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $actor->id, 'role' => CommunityMember::ROLE_MEMBER]);
        $target = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $target->id]);

        $this->expectException(AuthorizationException::class);
        $this->action->execute($actor, $community, $target);
    }
}
