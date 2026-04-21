<?php

namespace Tests\Feature\Web;

use App\Ai\Agents\CurzzoBot;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Curzzo;
use App\Models\CurzzoMessage;
use App\Models\CurzzoPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Tests\TestCase;

class CurzzoChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeFreeMember(Community $community): User
    {
        $user = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        return $user;
    }

    // ─── chat ────────────────────────────────────────────────────────────────

    public function test_chat_persists_user_and_assistant_messages(): void
    {
        Ai::fakeAgent(CurzzoBot::class, ['Hello from the bot.']);

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'Hi there',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Hello from the bot.')
            ->assertJsonStructure(['message', 'conversation_id', 'daily_limit', 'daily_used', 'topup_remaining']);

        $this->assertDatabaseHas('curzzo_messages', [
            'curzzo_id' => $curzzo->id,
            'user_id' => $member->id,
            'role' => 'user',
            'content' => 'Hi there',
        ]);
        $this->assertDatabaseHas('curzzo_messages', [
            'curzzo_id' => $curzzo->id,
            'user_id' => $member->id,
            'role' => 'assistant',
            'content' => 'Hello from the bot.',
        ]);
    }

    public function test_chat_requires_message_field(): void
    {
        Ai::fakeAgent(CurzzoBot::class, ['ok']);

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_chat_returns_404_for_inactive_curzzo(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->inactive()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'Hi',
            ])
            ->assertNotFound();
    }

    public function test_chat_returns_404_for_curzzo_from_other_community(): void
    {
        $owner = User::factory()->create();
        $communityA = Community::factory()->create(['owner_id' => $owner->id]);
        $communityB = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzoB = Curzzo::factory()->create(['community_id' => $communityB->id]);
        $member = $this->makeFreeMember($communityA);

        $this->actingAs($member)
            ->postJson("/communities/{$communityA->slug}/curzzos/{$curzzoB->id}/chat", [
                'message' => 'Hi',
            ])
            ->assertNotFound();
    }

    public function test_chat_denies_access_to_paid_curzzo_without_purchase(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->paidOnce()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'Hi',
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'Purchase required to chat with this Curzzo.');
    }

    public function test_chat_allows_paid_curzzo_with_active_purchase(): void
    {
        Ai::fakeAgent(CurzzoBot::class, ['Welcome buyer.']);

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->paidOnce()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        CurzzoPurchase::create([
            'user_id' => $member->id,
            'curzzo_id' => $curzzo->id,
            'status' => CurzzoPurchase::STATUS_PAID,
            'expires_at' => null,
        ]);

        $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'Hi',
            ])
            ->assertOk();
    }

    public function test_chat_allows_community_owner_to_bypass_purchase_check(): void
    {
        Ai::fakeAgent(CurzzoBot::class, ['Owner can chat.']);

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->paidOnce()->create(['community_id' => $community->id]);

        $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'Testing my bot',
            ])
            ->assertOk();
    }

    public function test_chat_returns_429_when_daily_limit_reached(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        // Fill up daily limit (10 for free members)
        for ($i = 0; $i < 10; $i++) {
            CurzzoMessage::create([
                'curzzo_id' => $curzzo->id,
                'community_id' => $community->id,
                'user_id' => $member->id,
                'role' => 'user',
                'content' => "msg $i",
            ]);
        }

        $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'One more',
            ])
            ->assertStatus(429)
            ->assertJsonPath('limit_reached', true);
    }

    public function test_chat_requires_auth(): void
    {
        $community = Community::factory()->create();
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);

        $this->postJson("/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
            'message' => 'Hi',
        ])->assertUnauthorized();
    }

    // ─── history ─────────────────────────────────────────────────────────────

    public function test_history_returns_current_user_messages_only(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);
        $other = $this->makeFreeMember($community);

        CurzzoMessage::create([
            'curzzo_id' => $curzzo->id,
            'community_id' => $community->id,
            'user_id' => $member->id,
            'role' => 'user',
            'content' => 'Mine',
        ]);
        CurzzoMessage::create([
            'curzzo_id' => $curzzo->id,
            'community_id' => $community->id,
            'user_id' => $other->id,
            'role' => 'user',
            'content' => 'Not mine',
        ]);

        $this->actingAs($member)
            ->getJson("/communities/{$community->slug}/curzzos/{$curzzo->id}/history")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.text', 'Mine');
    }

    public function test_history_returns_404_for_curzzo_from_other_community(): void
    {
        $owner = User::factory()->create();
        $communityA = Community::factory()->create(['owner_id' => $owner->id]);
        $communityB = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzoB = Curzzo::factory()->create(['community_id' => $communityB->id]);
        $member = $this->makeFreeMember($communityA);

        $this->actingAs($member)
            ->getJson("/communities/{$communityA->slug}/curzzos/{$curzzoB->id}/history")
            ->assertNotFound();
    }

    // ─── resetHistory ────────────────────────────────────────────────────────

    public function test_reset_history_deletes_only_current_user_messages(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);
        $other = $this->makeFreeMember($community);

        CurzzoMessage::create([
            'curzzo_id' => $curzzo->id,
            'community_id' => $community->id,
            'user_id' => $member->id,
            'role' => 'user',
            'content' => 'Mine',
        ]);
        CurzzoMessage::create([
            'curzzo_id' => $curzzo->id,
            'community_id' => $community->id,
            'user_id' => $other->id,
            'role' => 'user',
            'content' => 'Not mine',
        ]);

        $this->actingAs($member)
            ->deleteJson("/communities/{$community->slug}/curzzos/{$curzzo->id}/history")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('curzzo_messages', ['user_id' => $member->id]);
        $this->assertDatabaseHas('curzzo_messages', ['user_id' => $other->id, 'content' => 'Not mine']);
    }
}
