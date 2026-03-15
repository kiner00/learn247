<?php

namespace Tests\Feature\Api;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_messages_returns_conversations_list(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        DirectMessage::create([
            'sender_id'   => $user->id,
            'receiver_id' => $other->id,
            'content'     => 'Hello',
        ]);

        $response = $this->actingAs($user)->getJson('/api/messages');

        $response->assertOk()->assertJsonStructure(['conversations']);
    }

    public function test_get_messages_with_user_returns_thread(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        DirectMessage::create([
            'sender_id'   => $user->id,
            'receiver_id' => $other->id,
            'content'     => 'Hello',
        ]);

        $response = $this->actingAs($user)->getJson("/api/messages/{$other->id}");

        $response->assertOk()
            ->assertJsonStructure(['partner', 'messages'])
            ->assertJsonPath('partner.id', $other->id)
            ->assertJsonPath('messages.0.content', 'Hello');
    }

    public function test_post_messages_sends_dm_returns_201(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/messages/{$other->id}", [
            'content' => 'Hello there',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message.content', 'Hello there')
            ->assertJsonPath('message.is_mine', true);
        $this->assertDatabaseHas('direct_messages', [
            'sender_id'   => $user->id,
            'receiver_id' => $other->id,
            'content'     => 'Hello there',
        ]);
    }

    public function test_post_messages_validation_error_without_content(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/messages/{$other->id}", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['content']);
    }

    public function test_get_messages_search_returns_messageable_users(): void
    {
        $user = User::factory()->create(['name' => 'John Doe', 'username' => 'johndoe']);
        User::factory()->create(['name' => 'Jane Smith', 'username' => 'janesmith']);

        $response = $this->actingAs($user)->getJson('/api/messages/search?q=john');

        $response->assertOk()->assertJsonStructure(['users']);
    }

    public function test_sender_can_delete_own_message(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $dm    = DirectMessage::create([
            'sender_id'   => $user->id,
            'receiver_id' => $other->id,
            'content'     => 'Hello',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/direct-messages/{$dm->id}");

        $response->assertOk()->assertJsonPath('deleted', $dm->id);
        $this->assertDatabaseMissing('direct_messages', ['id' => $dm->id]);
    }

    public function test_non_sender_cannot_delete_message_returns_403(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $dm    = DirectMessage::create([
            'sender_id'   => $user->id,
            'receiver_id' => $other->id,
            'content'     => 'Hello',
        ]);

        $response = $this->actingAs($other)->deleteJson("/api/direct-messages/{$dm->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('direct_messages', ['id' => $dm->id]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/messages')->assertUnauthorized();
        $this->postJson('/api/messages/1', ['content' => 'Hi'])->assertUnauthorized();
    }

    public function test_poll_returns_new_messages_from_partner(): void
    {
        $user    = User::factory()->create();
        $partner = User::factory()->create();

        $dm = DirectMessage::create([
            'sender_id'   => $partner->id,
            'receiver_id' => $user->id,
            'content'     => 'New message from partner',
        ]);

        $response = $this->actingAs($user)->getJson("/api/messages/{$partner->id}/poll?after=0");

        $response->assertOk()
            ->assertJsonStructure(['messages'])
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.content', 'New message from partner')
            ->assertJsonPath('messages.0.is_mine', false);
    }
}
