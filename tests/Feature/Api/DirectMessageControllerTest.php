<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_messages_returns_conversations_list(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        DirectMessage::create([
            'sender_id' => $user->id,
            'receiver_id' => $other->id,
            'content' => 'Hello',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/messages');

        $response->assertOk()->assertJsonStructure(['conversations']);
    }

    public function test_get_messages_with_user_returns_thread(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        DirectMessage::create([
            'sender_id' => $user->id,
            'receiver_id' => $other->id,
            'content' => 'Hello',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/messages/{$other->id}");

        $response->assertOk()
            ->assertJsonStructure(['partner', 'messages'])
            ->assertJsonPath('partner.id', $other->id)
            ->assertJsonPath('messages.0.content', 'Hello');
    }

    public function test_post_messages_sends_dm_returns_201(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/v1/messages/{$other->id}", [
            'content' => 'Hello there',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message.content', 'Hello there')
            ->assertJsonPath('message.is_mine', true);
        $this->assertDatabaseHas('direct_messages', [
            'sender_id' => $user->id,
            'receiver_id' => $other->id,
            'content' => 'Hello there',
        ]);
    }

    public function test_post_messages_validation_error_without_content(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/v1/messages/{$other->id}", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['content']);
    }

    public function test_get_messages_search_returns_messageable_users(): void
    {
        $user = User::factory()->create(['name' => 'John Doe', 'username' => 'johndoe']);
        User::factory()->create(['name' => 'Jane Smith', 'username' => 'janesmith']);

        $response = $this->actingAs($user)->getJson('/api/v1/messages/search?q=john');

        $response->assertOk()->assertJsonStructure(['users']);
    }

    public function test_sender_can_delete_own_message(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $dm = DirectMessage::create([
            'sender_id' => $user->id,
            'receiver_id' => $other->id,
            'content' => 'Hello',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/direct-messages/{$dm->id}");

        $response->assertOk()->assertJsonPath('deleted', $dm->id);
        $this->assertDatabaseMissing('direct_messages', ['id' => $dm->id]);
    }

    public function test_non_sender_cannot_delete_message_returns_403(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $dm = DirectMessage::create([
            'sender_id' => $user->id,
            'receiver_id' => $other->id,
            'content' => 'Hello',
        ]);

        $response = $this->actingAs($other)->deleteJson("/api/v1/direct-messages/{$dm->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('direct_messages', ['id' => $dm->id]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/messages')->assertUnauthorized();
        $this->postJson('/api/v1/messages/1', ['content' => 'Hi'])->assertUnauthorized();
    }

    public function test_poll_returns_new_messages_from_partner(): void
    {
        $user = User::factory()->create();
        $partner = User::factory()->create();

        $dm = DirectMessage::create([
            'sender_id' => $partner->id,
            'receiver_id' => $user->id,
            'content' => 'New message from partner',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/messages/{$partner->id}/poll?after=0");

        $response->assertOk()
            ->assertJsonStructure(['messages'])
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.content', 'New message from partner')
            ->assertJsonPath('messages.0.is_mine', false);
    }

    public function test_search_with_empty_query_returns_users_from_same_community(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);
        $other = User::factory()->create(['name' => 'Bob']);
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $other->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/messages/search');

        $response->assertOk()
            ->assertJsonStructure(['users'])
            ->assertJsonCount(1, 'users');
    }

    public function test_show_with_empty_conversation_returns_empty_messages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/v1/messages/{$other->id}");

        $response->assertOk()
            ->assertJsonStructure(['partner', 'messages'])
            ->assertJsonPath('partner.id', $other->id)
            ->assertJsonCount(0, 'messages');
    }

    public function test_poll_with_no_new_messages_returns_empty(): void
    {
        $user = User::factory()->create();
        $partner = User::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/v1/messages/{$partner->id}/poll?after=99999");

        $response->assertOk()
            ->assertJsonStructure(['messages'])
            ->assertJsonCount(0, 'messages');
    }

    public function test_search_filters_by_username(): void
    {
        $user = User::factory()->create(['username' => 'alice123',   'name' => 'Alice Test']);
        $match = User::factory()->create(['username' => 'alice_other', 'name' => 'Alice Match']);
        $nomatch = User::factory()->create(['username' => 'bob456',      'name' => 'Bob Smith']);
        $community = Community::factory()->create();
        foreach ([$user, $match, $nomatch] as $u) {
            CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $u->id]);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/messages/search?q=alice');

        $response->assertOk()->assertJsonStructure(['users']);
        $usernames = array_column($response->json('users'), 'username');
        $this->assertContains('alice_other', $usernames);
        $this->assertNotContains('bob456', $usernames);
    }
}
