<?php

namespace Tests\Feature\Actions\Chat;

use App\Actions\Chat\SendChatMessage;
use App\Jobs\ForwardMessageToTelegram;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SendChatMessageTest extends TestCase
{
    use RefreshDatabase;

    private function getJobProperty(ForwardMessageToTelegram $job, string $property): mixed
    {
        $ref = new \ReflectionProperty($job, $property);

        return $ref->getValue($job);
    }

    public function test_creates_message_in_database(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action  = new SendChatMessage();
        $message = $action->execute($user, $community, 'Hello world');

        $this->assertInstanceOf(Message::class, $message);
        $this->assertDatabaseHas('messages', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Hello world',
        ]);
    }

    public function test_updates_messages_last_read_at(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);
        CommunityMember::factory()->create([
            'community_id'          => $community->id,
            'user_id'               => $user->id,
            'messages_last_read_at' => null,
        ]);

        $action = new SendChatMessage();
        $action->execute($user, $community, 'Test message');

        $this->assertNotNull(
            CommunityMember::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->value('messages_last_read_at')
        );
    }

    public function test_returns_message_with_user_relation_loaded(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action  = new SendChatMessage();
        $message = $action->execute($user, $community, 'Test');

        $this->assertTrue($message->relationLoaded('user'));
        $this->assertEquals($user->id, $message->user->id);
    }

    public function test_stores_media_url_and_type(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action  = new SendChatMessage();
        $message = $action->execute($user, $community, 'See image', 'https://example.com/photo.jpg', 'image');

        $this->assertDatabaseHas('messages', [
            'id'         => $message->id,
            'media_url'  => 'https://example.com/photo.jpg',
            'media_type' => 'image',
        ]);
    }

    public function test_sends_telegram_text_message_when_configured(): void
    {
        Bus::fake([ForwardMessageToTelegram::class]);

        $user      = User::factory()->create(['name' => 'Alice']);
        $community = Community::factory()->create([
            'owner_id'           => $user->id,
            'telegram_bot_token' => 'bot-token-123',
            'telegram_chat_id'   => 'chat-456',
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action = new SendChatMessage();
        $action->execute($user, $community, 'Hello telegram');

        Bus::assertDispatched(ForwardMessageToTelegram::class, function (ForwardMessageToTelegram $job) {
            return $this->getJobProperty($job, 'token') === 'bot-token-123'
                && $this->getJobProperty($job, 'chatId') === 'chat-456'
                && str_contains($this->getJobProperty($job, 'caption'), 'Admin')
                && str_contains($this->getJobProperty($job, 'caption'), 'Alice')
                && $this->getJobProperty($job, 'mediaUrl') === null
                && $this->getJobProperty($job, 'mediaType') === null;
        });
    }

    public function test_sends_telegram_photo_when_media_type_is_image(): void
    {
        Bus::fake([ForwardMessageToTelegram::class]);

        $user      = User::factory()->create(['name' => 'Bob']);
        $community = Community::factory()->create([
            'owner_id'           => User::factory()->create()->id,
            'telegram_bot_token' => 'bot-token',
            'telegram_chat_id'   => 'chat-id',
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action = new SendChatMessage();
        $action->execute($user, $community, 'Check this', 'https://example.com/photo.jpg', 'image');

        Bus::assertDispatched(ForwardMessageToTelegram::class, function (ForwardMessageToTelegram $job) {
            return $this->getJobProperty($job, 'token') === 'bot-token'
                && $this->getJobProperty($job, 'mediaUrl') === 'https://example.com/photo.jpg'
                && $this->getJobProperty($job, 'mediaType') === 'image'
                && str_contains($this->getJobProperty($job, 'caption'), 'Member')
                && str_contains($this->getJobProperty($job, 'caption'), 'Bob');
        });
    }

    public function test_sends_telegram_video_when_media_type_is_video(): void
    {
        Bus::fake([ForwardMessageToTelegram::class]);

        $user      = User::factory()->create(['name' => 'Carol']);
        $community = Community::factory()->create([
            'owner_id'           => $user->id,
            'telegram_bot_token' => 'bot-token',
            'telegram_chat_id'   => 'chat-id',
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action = new SendChatMessage();
        $action->execute($user, $community, 'Watch this', 'https://example.com/video.mp4', 'video');

        Bus::assertDispatched(ForwardMessageToTelegram::class, function (ForwardMessageToTelegram $job) {
            return $this->getJobProperty($job, 'mediaUrl') === 'https://example.com/video.mp4'
                && $this->getJobProperty($job, 'mediaType') === 'video'
                && str_contains($this->getJobProperty($job, 'caption'), 'Admin');
        });
    }

    public function test_does_not_send_telegram_when_not_configured(): void
    {
        Bus::fake([ForwardMessageToTelegram::class]);

        $user      = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'           => $user->id,
            'telegram_bot_token' => null,
            'telegram_chat_id'   => null,
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action = new SendChatMessage();
        $action->execute($user, $community, 'No telegram');

        Bus::assertNotDispatched(ForwardMessageToTelegram::class);
    }

    public function test_telegram_prefix_shows_member_for_non_owner(): void
    {
        Bus::fake([ForwardMessageToTelegram::class]);

        $owner     = User::factory()->create();
        $member    = User::factory()->create(['name' => 'Dave']);
        $community = Community::factory()->create([
            'owner_id'           => $owner->id,
            'telegram_bot_token' => 'token',
            'telegram_chat_id'   => 'chat',
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $action = new SendChatMessage();
        $action->execute($member, $community, 'Member message');

        Bus::assertDispatched(ForwardMessageToTelegram::class, function (ForwardMessageToTelegram $job) {
            $caption = $this->getJobProperty($job, 'caption');

            return str_contains($caption, 'Member') && str_contains($caption, 'Dave');
        });
    }

    public function test_logs_warning_when_broadcast_fails_but_still_returns_message(): void
    {
        // Force broadcasting to use a driver that will throw (pusher with missing creds)
        config([
            'broadcasting.default'            => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key'    => '',
                'secret' => '',
                'app_id' => '',
                'options' => [],
            ],
        ]);

        \Illuminate\Support\Facades\Log::spy();

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action  = new SendChatMessage();
        $message = $action->execute($user, $community, 'Hello broadcast');

        // Message still created & returned even though broadcast blew up
        $this->assertInstanceOf(Message::class, $message);
        $this->assertDatabaseHas('messages', [
            'id'      => $message->id,
            'content' => 'Hello broadcast',
        ]);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->with('Chat broadcast failed', \Mockery::type('array'))
            ->atLeast()->once();
    }

    public function test_sends_photo_with_empty_content(): void
    {
        Bus::fake([ForwardMessageToTelegram::class]);

        $user      = User::factory()->create(['name' => 'Eve']);
        $community = Community::factory()->create([
            'owner_id'           => $user->id,
            'telegram_bot_token' => 'token',
            'telegram_chat_id'   => 'chat',
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action = new SendChatMessage();
        $action->execute($user, $community, '', 'https://example.com/photo.jpg', 'image');

        Bus::assertDispatched(ForwardMessageToTelegram::class, function (ForwardMessageToTelegram $job) {
            $caption = $this->getJobProperty($job, 'caption');

            return str_contains($caption, 'Admin')
                && str_contains($caption, 'Eve')
                && $this->getJobProperty($job, 'mediaUrl') === 'https://example.com/photo.jpg'
                && $this->getJobProperty($job, 'mediaType') === 'image';
        });
    }
}
