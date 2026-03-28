<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createCommunityWithMember(): array
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        return [$owner, $community, $member];
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function test_member_can_view_chat(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/chat");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Communities/Chat')
            ->has('community')
            ->has('messages')
        );
    }

    public function test_owner_can_view_chat(): void
    {
        [$owner, $community] = $this->createCommunityWithMember();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/chat");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Communities/Chat'));
    }

    public function test_non_member_is_denied_chat_access(): void
    {
        [$owner, $community] = $this->createCommunityWithMember();
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)
            ->get("/communities/{$community->slug}/chat");

        $response->assertRedirect();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $response = $this->get("/communities/{$community->slug}/chat");

        $response->assertRedirect('/login');
    }

    public function test_index_marks_messages_as_read(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        Message::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'content'      => 'Hello chat',
        ]);

        $this->actingAs($member)
            ->get("/communities/{$community->slug}/chat");

        $this->assertNotNull(
            CommunityMember::where('community_id', $community->id)
                ->where('user_id', $member->id)
                ->value('messages_last_read_at')
        );
    }

    // ─── store ─────────────────────────────────────────────────────────────────

    public function test_member_can_send_chat_message(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/chat", [
                'content' => 'Hey everyone!',
            ]);

        $response->assertOk();
        $response->assertJsonPath('message.content', 'Hey everyone!');
        $response->assertJsonStructure([
            'message' => ['id', 'content', 'created_at', 'user' => ['id', 'name', 'username']],
        ]);

        $this->assertDatabaseHas('messages', [
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'content'      => 'Hey everyone!',
        ]);
    }

    public function test_owner_can_send_chat_message(): void
    {
        [$owner, $community] = $this->createCommunityWithMember();

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/chat", [
                'content' => 'Owner message',
            ]);

        $response->assertOk();
        $response->assertJsonPath('message.content', 'Owner message');
    }

    public function test_store_validates_content_required(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/chat", [
                'content' => '',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('content');
    }

    public function test_store_validates_content_max_length(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/chat", [
                'content' => str_repeat('x', 2001),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('content');
    }

    public function test_non_member_cannot_send_chat_message(): void
    {
        [$owner, $community] = $this->createCommunityWithMember();
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)
            ->postJson("/communities/{$community->slug}/chat", [
                'content' => 'Should fail',
            ]);

        $response->assertForbidden();
    }

    // ─── poll ──────────────────────────────────────────────────────────────────

    public function test_member_can_poll_for_new_messages(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        $msg = Message::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'content'      => 'New chat message',
        ]);

        $response = $this->actingAs($member)
            ->getJson("/communities/{$community->slug}/chat/poll?after=0");

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonFragment(['content' => 'New chat message']);
    }

    public function test_poll_returns_only_messages_after_given_id(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        $old = Message::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'content'      => 'Old',
        ]);

        $new = Message::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'content'      => 'New',
        ]);

        $response = $this->actingAs($member)
            ->getJson("/communities/{$community->slug}/chat/poll?after={$old->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonFragment(['content' => 'New']);
    }

    public function test_poll_returns_empty_when_no_new_messages(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        $response = $this->actingAs($member)
            ->getJson("/communities/{$community->slug}/chat/poll?after=0");

        $response->assertOk();
        $response->assertJsonCount(0, 'messages');
    }

    public function test_non_member_cannot_poll_chat(): void
    {
        [$owner, $community] = $this->createCommunityWithMember();
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)
            ->getJson("/communities/{$community->slug}/chat/poll?after=0");

        $response->assertForbidden();
    }

    // ─── destroy ───────────────────────────────────────────────────────────────

    public function test_author_can_delete_own_chat_message(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        $msg = Message::create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'content'      => 'Delete me',
        ]);

        $response = $this->actingAs($member)
            ->deleteJson("/communities/{$community->slug}/chat/{$msg->id}");

        $response->assertOk();
        $response->assertJsonPath('deleted', $msg->id);
        $this->assertDatabaseMissing('messages', ['id' => $msg->id]);
    }

    public function test_member_cannot_delete_others_chat_message(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();
        $anotherMember = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $anotherMember->id]);

        $msg = Message::create([
            'community_id' => $community->id,
            'user_id'      => $anotherMember->id,
            'content'      => 'Not yours',
        ]);

        $response = $this->actingAs($member)
            ->deleteJson("/communities/{$community->slug}/chat/{$msg->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('messages', ['id' => $msg->id]);
    }

    public function test_super_admin_can_delete_any_chat_message(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();
        $admin = User::factory()->create(['is_super_admin' => true]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $admin->id]);

        $msg = Message::create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'content'      => 'Any message',
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/communities/{$community->slug}/chat/{$msg->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('messages', ['id' => $msg->id]);
    }

    public function test_non_member_cannot_delete_chat_message(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();
        $stranger = User::factory()->create();

        $msg = Message::create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'content'      => 'Some message',
        ]);

        $response = $this->actingAs($stranger)
            ->deleteJson("/communities/{$community->slug}/chat/{$msg->id}");

        $response->assertForbidden();
    }

    // ─── blocked member ───────────────────────────────────────────────────────

    public function test_blocked_member_cannot_send_chat_message(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        CommunityMember::where('community_id', $community->id)
            ->where('user_id', $member->id)
            ->update(['is_blocked' => true]);

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/chat", [
                'content' => 'I am blocked',
            ]);

        $response->assertForbidden();
        $response->assertJsonPath('error', 'You have been blocked from chatting in this community.');
    }

    // ─── media upload ─────────────────────────────────────────────────────────

    public function test_member_can_send_image_media(): void
    {
        Storage::fake(config('filesystems.default'));

        [$owner, $community, $member] = $this->createCommunityWithMember();

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/chat", [
                'content' => 'Check this image',
                'media'   => UploadedFile::fake()->image('photo.jpg'),
            ]);

        $response->assertOk();
        $response->assertJsonPath('message.media_type', 'image');
        $this->assertNotNull($response->json('message.media_url'));
    }

    public function test_member_can_send_video_media(): void
    {
        Storage::fake(config('filesystems.default'));

        [$owner, $community, $member] = $this->createCommunityWithMember();

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/chat", [
                'content' => 'Check this video',
                'media'   => UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4'),
            ]);

        $response->assertOk();
        $response->assertJsonPath('message.media_type', 'video');
        $this->assertNotNull($response->json('message.media_url'));
    }

    // ─── poll marks messages as read ──────────────────────────────────────────

    public function test_poll_marks_messages_as_read(): void
    {
        [$owner, $community, $member] = $this->createCommunityWithMember();

        Message::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'content'      => 'New message',
        ]);

        $this->actingAs($member)
            ->getJson("/communities/{$community->slug}/chat/poll?after=0");

        $this->assertNotNull(
            CommunityMember::where('community_id', $community->id)
                ->where('user_id', $member->id)
                ->value('messages_last_read_at')
        );
    }
}
