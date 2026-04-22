<?php

namespace Tests\Feature\Api;

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

    public function test_chat_persists_messages_and_returns_payload(): void
    {
        Ai::fakeAgent(CurzzoBot::class, ['Hi from API']);

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'Hello',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Hi from API')
            ->assertJsonStructure(['message', 'conversation_id', 'daily_limit', 'daily_used', 'topup_remaining']);

        $this->assertDatabaseHas('curzzo_messages', [
            'user_id' => $member->id,
            'role' => 'user',
            'content' => 'Hello',
        ]);
    }

    public function test_chat_requires_auth(): void
    {
        $community = Community::factory()->create();
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);

        $this->postJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
            'message' => 'Hi',
        ])->assertUnauthorized();
    }

    public function test_chat_validates_message(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_chat_404s_on_inactive_curzzo(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->inactive()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'Hi',
            ])
            ->assertNotFound();
    }

    public function test_chat_403s_on_paid_curzzo_without_purchase(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->paidOnce()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'Hi',
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'Purchase required to chat with this Curzzo.');
    }

    public function test_chat_owner_bypasses_purchase_check(): void
    {
        Ai::fakeAgent(CurzzoBot::class, ['owner ok']);

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->paidOnce()->create(['community_id' => $community->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'Hi',
            ])
            ->assertOk();
    }

    public function test_chat_429s_on_daily_limit_reached(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        for ($i = 0; $i < 10; $i++) {
            CurzzoMessage::create([
                'curzzo_id' => $curzzo->id,
                'community_id' => $community->id,
                'user_id' => $member->id,
                'role' => 'user',
                'content' => "m$i",
            ]);
        }

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}/chat", [
                'message' => 'one more',
            ])
            ->assertStatus(429)
            ->assertJsonPath('limit_reached', true);
    }

    // ─── history ─────────────────────────────────────────────────────────────

    public function test_history_returns_user_messages(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        CurzzoMessage::create([
            'curzzo_id' => $curzzo->id,
            'community_id' => $community->id,
            'user_id' => $member->id,
            'role' => 'user',
            'content' => 'mine',
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}/history")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.text', 'mine')
            ->assertJsonPath('data.0.role', 'user');
    }

    // ─── resetHistory ────────────────────────────────────────────────────────

    public function test_reset_history_deletes_user_messages(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $member = $this->makeFreeMember($community);

        CurzzoMessage::create([
            'curzzo_id' => $curzzo->id,
            'community_id' => $community->id,
            'user_id' => $member->id,
            'role' => 'user',
            'content' => 'mine',
        ]);

        $this->actingAs($member, 'sanctum')
            ->deleteJson("/api/v1/communities/{$community->slug}/curzzos/{$curzzo->id}/history")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('curzzo_messages', ['user_id' => $member->id]);
    }
}
