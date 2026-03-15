<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Message;
use App\Models\Subscription;
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

    public function test_owner_can_access_chat_without_membership(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        Message::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'content'      => 'Owner message',
        ]);

        $this->actingAs($owner)
            ->getJson("/api/communities/{$community->slug}/chat")
            ->assertOk();
    }

    public function test_non_author_cannot_delete_message(): void
    {
        $author    = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $author->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $message = Message::create([
            'community_id' => $community->id,
            'user_id'      => $author->id,
            'content'      => 'Author message',
        ]);

        $this->actingAs($other)
            ->deleteJson("/api/communities/{$community->slug}/chat/{$message->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_paid_subscriber_can_access_chat(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'xendit_id'    => 'inv_chat_paid',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        Message::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'content'      => 'Welcome to paid chat',
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/chat")
            ->assertOk();
    }

    public function test_non_subscriber_denied_paid_community_chat(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/chat")
            ->assertForbidden();
    }

    public function test_paid_subscriber_can_poll_chat(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'xendit_id'    => 'inv_chat_poll',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/chat/poll?after=0")
            ->assertOk();
    }

    public function test_paid_subscriber_can_send_message(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'xendit_id'    => 'inv_chat_send',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/chat", ['content' => 'Paid message'])
            ->assertCreated();
    }

    public function test_paid_subscriber_can_delete_message(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'xendit_id'    => 'inv_chat_del',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        $message = Message::create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'content'      => 'Delete me',
        ]);

        $this->actingAs($member, 'sanctum')
            ->deleteJson("/api/communities/{$community->slug}/chat/{$message->id}")
            ->assertOk();
    }

    public function test_owner_can_access_paid_community_chat(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/chat")
            ->assertOk();
    }

    public function test_expired_subscriber_denied_paid_community_chat(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'xendit_id'    => 'inv_chat_expired',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->subDay(),
        ]);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/chat")
            ->assertForbidden();
    }

    public function test_index_with_positive_after_returns_newer_messages(): void
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
            ->getJson("/api/communities/{$community->slug}/chat?after={$old->id}")
            ->assertOk()
            ->assertJsonStructure(['messages']);
    }
}
