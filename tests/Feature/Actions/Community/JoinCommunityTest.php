<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\JoinCommunity;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JoinCommunityTest extends TestCase
{
    use RefreshDatabase;

    private JoinCommunity $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new JoinCommunity();
    }

    public function test_user_can_join_free_community(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $member = $this->action->execute($user, $community);

        $this->assertInstanceOf(CommunityMember::class, $member);
        $this->assertEquals(CommunityMember::ROLE_MEMBER, $member->role);
        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
    }

    public function test_throws_if_already_a_member(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $community);
    }

    public function test_throws_if_community_is_paid(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $community);
    }
}
