<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithUsername(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'username' => 'user_' . uniqid(),
        ], $attributes));
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_conversations_list(): void
    {
        $user    = $this->createUserWithUsername();
        $partner = $this->createUserWithUsername();

        DirectMessage::create([
            'sender_id'   => $user->id,
            'receiver_id' => $partner->id,
            'content'     => 'Hello!',
        ]);

        $response = $this->actingAs($user)->get('/messages');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Messages/Index')
            ->has('conversations')
        );
    }

    public function test_index_returns_json_when_requested(): void
    {
        $user = $this->createUserWithUsername();

        $response = $this->actingAs($user)->getJson('/messages');

        $response->assertOk();
        $response->assertJsonStructure(['conversations']);
    }

    public function test_guest_cannot_view_messages_index(): void
    {
        $response = $this->get('/messages');

        $response->assertRedirect('/login');
    }

    // ─── search ────────────────────────────────────────────────────────────────

    public function test_user_can_search_messageable_users(): void
    {
        $owner     = $this->createUserWithUsername();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $user   = $this->createUserWithUsername(['name' => 'John Doe']);
        $target = $this->createUserWithUsername(['name' => 'Jane Smith']);

        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $target->id]);

        $response = $this->actingAs($user)->getJson('/users/search?q=Jane');

        $response->assertOk();
        $response->assertJsonStructure(['users']);
        $response->assertJsonFragment(['name' => 'Jane Smith']);
    }

    public function test_search_does_not_return_self(): void
    {
        $owner     = $this->createUserWithUsername();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $user = $this->createUserWithUsername(['name' => 'John Doe']);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/users/search?q=John');

        $response->assertOk();
        $response->assertJsonMissing(['name' => 'John Doe']);
    }

    public function test_guest_cannot_search_users(): void
    {
        $response = $this->getJson('/users/search?q=test');

        $response->assertUnauthorized();
    }

    // ─── show ──────────────────────────────────────────────────────────────────

    public function test_user_can_view_conversation_thread(): void
    {
        $user    = $this->createUserWithUsername();
        $partner = $this->createUserWithUsername();

        DirectMessage::create([
            'sender_id'   => $user->id,
            'receiver_id' => $partner->id,
            'content'     => 'Hey there',
        ]);

        $response = $this->actingAs($user)->get("/messages/{$partner->username}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Messages/Show')
            ->has('partner')
            ->has('messages')
        );
    }

    public function test_show_marks_unread_messages_as_read(): void
    {
        $user    = $this->createUserWithUsername();
        $partner = $this->createUserWithUsername();

        DirectMessage::create([
            'sender_id'   => $partner->id,
            'receiver_id' => $user->id,
            'content'     => 'Unread message',
        ]);

        $this->actingAs($user)->get("/messages/{$partner->username}");

        $this->assertDatabaseMissing('direct_messages', [
            'sender_id'   => $partner->id,
            'receiver_id' => $user->id,
            'read_at'     => null,
        ]);
    }

    public function test_guest_cannot_view_conversation(): void
    {
        $partner = $this->createUserWithUsername();

        $response = $this->get("/messages/{$partner->username}");

        $response->assertRedirect('/login');
    }

    // ─── store ─────────────────────────────────────────────────────────────────

    public function test_user_can_send_direct_message(): void
    {
        $sender   = $this->createUserWithUsername();
        $receiver = $this->createUserWithUsername();

        $response = $this->actingAs($sender)
            ->postJson("/messages/{$receiver->username}", [
                'content' => 'Hello friend!',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['content' => 'Hello friend!']);
        $response->assertJsonPath('message.is_mine', true);

        $this->assertDatabaseHas('direct_messages', [
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'content'     => 'Hello friend!',
        ]);
    }

    public function test_store_validates_content_required(): void
    {
        $sender   = $this->createUserWithUsername();
        $receiver = $this->createUserWithUsername();

        $response = $this->actingAs($sender)
            ->postJson("/messages/{$receiver->username}", [
                'content' => '',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('content');
    }

    public function test_store_validates_content_max_length(): void
    {
        $sender   = $this->createUserWithUsername();
        $receiver = $this->createUserWithUsername();

        $response = $this->actingAs($sender)
            ->postJson("/messages/{$receiver->username}", [
                'content' => str_repeat('a', 2001),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('content');
    }

    public function test_guest_cannot_send_message(): void
    {
        $receiver = $this->createUserWithUsername();

        $response = $this->postJson("/messages/{$receiver->username}", [
            'content' => 'Hello',
        ]);

        $response->assertUnauthorized();
    }

    // ─── poll ──────────────────────────────────────────────────────────────────

    public function test_user_can_poll_for_new_messages(): void
    {
        $user    = $this->createUserWithUsername();
        $partner = $this->createUserWithUsername();

        $msg = DirectMessage::create([
            'sender_id'   => $partner->id,
            'receiver_id' => $user->id,
            'content'     => 'New message',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/messages/{$partner->username}/poll?after=0");

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonFragment(['content' => 'New message']);
        $response->assertJsonPath('messages.0.is_mine', false);
    }

    public function test_poll_returns_only_messages_after_given_id(): void
    {
        $user    = $this->createUserWithUsername();
        $partner = $this->createUserWithUsername();

        $old = DirectMessage::create([
            'sender_id'   => $partner->id,
            'receiver_id' => $user->id,
            'content'     => 'Old message',
        ]);

        $new = DirectMessage::create([
            'sender_id'   => $partner->id,
            'receiver_id' => $user->id,
            'content'     => 'New message',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/messages/{$partner->username}/poll?after={$old->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonFragment(['content' => 'New message']);
    }

    public function test_poll_marks_received_messages_as_read(): void
    {
        $user    = $this->createUserWithUsername();
        $partner = $this->createUserWithUsername();

        DirectMessage::create([
            'sender_id'   => $partner->id,
            'receiver_id' => $user->id,
            'content'     => 'Unread',
        ]);

        $this->actingAs($user)
            ->getJson("/messages/{$partner->username}/poll?after=0");

        $this->assertDatabaseMissing('direct_messages', [
            'sender_id'   => $partner->id,
            'receiver_id' => $user->id,
            'read_at'     => null,
        ]);
    }

    public function test_poll_returns_empty_when_no_new_messages(): void
    {
        $user    = $this->createUserWithUsername();
        $partner = $this->createUserWithUsername();

        $response = $this->actingAs($user)
            ->getJson("/messages/{$partner->username}/poll?after=0");

        $response->assertOk();
        $response->assertJsonCount(0, 'messages');
    }

    // ─── destroy ───────────────────────────────────────────────────────────────

    public function test_sender_can_delete_own_message(): void
    {
        $sender   = $this->createUserWithUsername();
        $receiver = $this->createUserWithUsername();

        $msg = DirectMessage::create([
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'content'     => 'Delete me',
        ]);

        $response = $this->actingAs($sender)
            ->deleteJson("/direct-messages/{$msg->id}");

        $response->assertOk();
        $response->assertJsonPath('deleted', $msg->id);
        $this->assertDatabaseMissing('direct_messages', ['id' => $msg->id]);
    }

    public function test_user_cannot_delete_others_message(): void
    {
        $sender   = $this->createUserWithUsername();
        $receiver = $this->createUserWithUsername();

        $msg = DirectMessage::create([
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'content'     => 'Not yours',
        ]);

        $response = $this->actingAs($receiver)
            ->deleteJson("/direct-messages/{$msg->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('direct_messages', ['id' => $msg->id]);
    }

    public function test_guest_cannot_delete_message(): void
    {
        $sender   = $this->createUserWithUsername();
        $receiver = $this->createUserWithUsername();

        $msg = DirectMessage::create([
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'content'     => 'Hello',
        ]);

        $response = $this->deleteJson("/direct-messages/{$msg->id}");

        $response->assertUnauthorized();
    }
}
