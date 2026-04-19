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
        $this->action = new JoinCommunity;
    }

    public function test_user_can_join_free_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $member = $this->action->execute($user, $community);

        $this->assertInstanceOf(CommunityMember::class, $member);
        $this->assertEquals(CommunityMember::ROLE_MEMBER, $member->role);
        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_throws_if_already_a_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $community);
    }

    public function test_throws_if_community_is_paid(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $community);
    }

    public function test_joining_already_joined_community_throws_exception(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $community);
    }

    public function test_joining_paid_community_throws_exception(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $this->expectException(ValidationException::class);
        $this->action->execute($user, $community);
    }

    public function test_joining_creates_notification_for_owner(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $this->action->execute($user, $community);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'actor_id' => $user->id,
            'type' => 'new_member',
        ]);
    }

    public function test_owner_joining_own_community_does_not_self_notify(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $this->action->execute($owner, $community);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $owner->id,
            'type' => 'new_member',
        ]);
    }

    public function test_milestone_notification_at_100_members(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        for ($i = 0; $i < 99; $i++) {
            CommunityMember::factory()->create(['community_id' => $community->id]);
        }

        $newUser = User::factory()->create();
        $this->action->execute($newUser, $community);

        $this->assertDatabaseHas('notifications', [
            'community_id' => $community->id,
            'type' => 'milestone',
        ]);
    }
}
