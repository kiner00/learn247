<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_get_chat_messages(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        Message::create([
            'community_id' => $community->id,
            'user_id'     => $user->id,
            'content'     => 'Hello',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/communities/{$community->slug}/chat");

        $response->assertOk()
            ->assertJsonStructure(['messages'])
            ->assertJsonPath('messages.0.content', 'Hello');
    }

    public function test_member_can_send_message(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/communities/{$community->slug}/chat", [
                'content' => 'Hello world',
            ]);

        $response->assertCreated()
            ->assertJsonPath('message.content', 'Hello world');

        $this->assertDatabaseHas('messages', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Hello world',
        ]);
    }

    public function test_send_message_validation_error_without_content(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson("/api/communities/{$community->slug}/chat", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_non_member_gets_403_for_chat(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->getJson("/api/communities/{$community->slug}/chat")
            ->assertForbidden();
    }

    public function test_author_can_delete_own_message(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $message = Message::create([
            'community_id' => $community->id,
            'user_id'     => $user->id,
            'content'     => 'Hello',
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/communities/{$community->slug}/chat/{$message->id}")
            ->assertOk()
            ->assertJsonPath('deleted', $message->id);

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_member_can_poll_for_new_messages(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $msg = Message::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Poll message',
        ]);

        $this->actingAs($user)
            ->getJson("/api/communities/{$community->slug}/chat/poll?after=0")
            ->assertOk()
            ->assertJsonStructure(['messages'])
            ->assertJsonCount(1, 'messages');
    }

    public function test_poll_with_after_returns_only_newer_messages(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $old = Message::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Old message',
        ]);

        $new = Message::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'New message',
        ]);

        $this->actingAs($user)
            ->getJson("/api/communities/{$community->slug}/chat/poll?after={$old->id}")
            ->assertOk()
            ->assertJsonCount(1, 'messages');
    }

    public function test_index_with_after_parameter(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $msg = Message::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Hello',
        ]);

        $this->actingAs($user)
            ->getJson("/api/communities/{$community->slug}/chat?after=0")
            ->assertOk()
            ->assertJsonStructure(['messages']);
    }
}
