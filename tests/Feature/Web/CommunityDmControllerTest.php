<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityDirectMessage;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityDmControllerTest extends TestCase
{
    use RefreshDatabase;

    private function membersInCommunity(): array
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $userA->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $userB->id,
        ]);

        return [$community, $userA, $userB];
    }

    // ─── conversations ──────────────────────────────────────────────────────────

    public function test_member_can_list_conversations(): void
    {
        [$community, $userA, $userB] = $this->membersInCommunity();

        CommunityDirectMessage::create([
            'community_id' => $community->id,
            'sender_id'    => $userA->id,
            'receiver_id'  => $userB->id,
            'content'      => 'Hello!',
        ]);

        $response = $this->actingAs($userA)
            ->getJson("/communities/{$community->slug}/dm/conversations");

        $response->assertOk();
        $response->assertJsonCount(1, 'conversations');
        $response->assertJsonPath('conversations.0.id', $userB->id);
    }

    public function test_conversations_returns_empty_when_no_messages(): void
    {
        [$community, $userA, $userB] = $this->membersInCommunity();

        $response = $this->actingAs($userA)
            ->getJson("/communities/{$community->slug}/dm/conversations");

        $response->assertOk();
        $response->assertJsonCount(0, 'conversations');
    }

    // ─── messages ───────────────────────────────────────────────────────────────

    public function test_member_can_get_messages_with_another_user(): void
    {
        [$community, $userA, $userB] = $this->membersInCommunity();

        CommunityDirectMessage::create([
            'community_id' => $community->id,
            'sender_id'    => $userA->id,
            'receiver_id'  => $userB->id,
            'content'      => 'Hey',
        ]);
        CommunityDirectMessage::create([
            'community_id' => $community->id,
            'sender_id'    => $userB->id,
            'receiver_id'  => $userA->id,
            'content'      => 'Hi back',
        ]);

        $response = $this->actingAs($userA)
            ->getJson("/communities/{$community->slug}/dm/{$userB->id}/messages");

        $response->assertOk();
        $response->assertJsonCount(2, 'messages');
        $response->assertJsonPath('messages.0.content', 'Hey');
        $response->assertJsonPath('messages.0.is_mine', true);
        $response->assertJsonPath('messages.1.is_mine', false);
    }

    // ─── send ───────────────────────────────────────────────────────────────────

    public function test_member_can_send_dm(): void
    {
        [$community, $userA, $userB] = $this->membersInCommunity();

        $response = $this->actingAs($userA)
            ->postJson("/communities/{$community->slug}/dm/send", [
                'receiver_id' => $userB->id,
                'content'     => 'Test message',
            ]);

        $response->assertOk();
        $response->assertJsonPath('message.content', 'Test message');
        $response->assertJsonPath('message.is_mine', true);

        $this->assertDatabaseHas('community_direct_messages', [
            'community_id' => $community->id,
            'sender_id'    => $userA->id,
            'receiver_id'  => $userB->id,
            'content'      => 'Test message',
        ]);
    }

    public function test_send_validates_required_fields(): void
    {
        [$community, $userA, $userB] = $this->membersInCommunity();

        $response = $this->actingAs($userA)
            ->postJson("/communities/{$community->slug}/dm/send", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['receiver_id', 'content']);
    }

    // ─── poll ───────────────────────────────────────────────────────────────────

    public function test_poll_returns_new_messages_after_given_id(): void
    {
        [$community, $userA, $userB] = $this->membersInCommunity();

        $msg1 = CommunityDirectMessage::create([
            'community_id' => $community->id,
            'sender_id'    => $userB->id,
            'receiver_id'  => $userA->id,
            'content'      => 'First',
        ]);
        $msg2 = CommunityDirectMessage::create([
            'community_id' => $community->id,
            'sender_id'    => $userB->id,
            'receiver_id'  => $userA->id,
            'content'      => 'Second',
        ]);

        $response = $this->actingAs($userA)
            ->getJson("/communities/{$community->slug}/dm/{$userB->id}/poll?after={$msg1->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonPath('messages.0.content', 'Second');
    }

    // ─── auth ───────────────────────────────────────────────────────────────────

    public function test_non_member_cannot_access_dm_conversations(): void
    {
        $owner     = User::factory()->create();
        $outsider  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $response = $this->actingAs($outsider)
            ->getJson("/communities/{$community->slug}/dm/conversations");

        // EnsureActiveMembership middleware returns 403 for JSON requests
        $response->assertForbidden();
    }
}
