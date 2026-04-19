<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\ToggleMemberBlock;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ToggleMemberBlockTest extends TestCase
{
    use RefreshDatabase;

    private ToggleMemberBlock $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ToggleMemberBlock;
    }

    public function test_owner_can_block_a_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $target = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $target->id,
            'is_blocked' => false,
        ]);

        $result = $this->action->execute($owner, $community, $target);

        $this->assertSame('blocked', $result);
        $this->assertTrue(CommunityMember::where('community_id', $community->id)->where('user_id', $target->id)->first()->is_blocked);
    }

    public function test_owner_can_unblock_a_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $target = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $target->id,
            'is_blocked' => true,
        ]);

        $result = $this->action->execute($owner, $community, $target);

        $this->assertSame('unblocked', $result);
    }

    public function test_regular_member_cannot_block(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $actor = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $actor->id,
            'role' => CommunityMember::ROLE_MEMBER,
        ]);

        $target = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $target->id]);

        $this->expectException(HttpException::class);
        $this->action->execute($actor, $community, $target);
    }

    public function test_cannot_block_community_owner(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $moderator = User::factory()->create();
        CommunityMember::factory()->moderator()->create(['community_id' => $community->id, 'user_id' => $moderator->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $this->expectException(HttpException::class);
        $this->action->execute($moderator, $community, $owner);
    }
}
