<?php

namespace Tests\Feature\Actions\Chat;

use App\Actions\Chat\SendChatMessage;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Message;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendChatMessageTest extends TestCase
{
    use RefreshDatabase;

    private function createAction(?TelegramService $telegram = null): SendChatMessage
    {
        $telegram ??= Mockery::mock(TelegramService::class)->shouldIgnoreMissing();

        return new SendChatMessage($telegram);
    }

    public function test_creates_message_in_database(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action = $this->createAction();
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

        $action = $this->createAction();
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

        $action  = $this->createAction();
        $message = $action->execute($user, $community, 'Test');

        $this->assertTrue($message->relationLoaded('user'));
        $this->assertEquals($user->id, $message->user->id);
    }

    public function test_stores_media_url_and_type(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action  = $this->createAction();
        $message = $action->execute($user, $community, 'See image', 'https://example.com/photo.jpg', 'image');

        $this->assertDatabaseHas('messages', [
            'id'         => $message->id,
            'media_url'  => 'https://example.com/photo.jpg',
            'media_type' => 'image',
        ]);
    }

    public function test_sends_telegram_text_message_when_configured(): void
    {
        $user      = User::factory()->create(['name' => 'Alice']);
        $community = Community::factory()->create([
            'owner_id'           => $user->id,
            'telegram_bot_token' => 'bot-token-123',
            'telegram_chat_id'   => 'chat-456',
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($token, $chatId, $text) {
                return $token === 'bot-token-123'
                    && $chatId === 'chat-456'
                    && str_contains($text, 'Admin')
                    && str_contains($text, 'Alice');
            });

        $action = new SendChatMessage($telegram);
        $action->execute($user, $community, 'Hello telegram');
    }

    public function test_sends_telegram_photo_when_media_type_is_image(): void
    {
        $user      = User::factory()->create(['name' => 'Bob']);
        $community = Community::factory()->create([
            'owner_id'           => User::factory()->create()->id,
            'telegram_bot_token' => 'bot-token',
            'telegram_chat_id'   => 'chat-id',
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendPhoto')
            ->once()
            ->withArgs(function ($token, $chatId, $photoUrl, $caption) {
                return $token === 'bot-token'
                    && $photoUrl === 'https://example.com/photo.jpg'
                    && str_contains($caption, 'Member')
                    && str_contains($caption, 'Bob');
            });

        $action = new SendChatMessage($telegram);
        $action->execute($user, $community, 'Check this', 'https://example.com/photo.jpg', 'image');
    }

    public function test_sends_telegram_video_when_media_type_is_video(): void
    {
        $user      = User::factory()->create(['name' => 'Carol']);
        $community = Community::factory()->create([
            'owner_id'           => $user->id,
            'telegram_bot_token' => 'bot-token',
            'telegram_chat_id'   => 'chat-id',
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendVideo')
            ->once()
            ->withArgs(function ($token, $chatId, $videoUrl, $caption) {
                return $videoUrl === 'https://example.com/video.mp4'
                    && str_contains($caption, 'Admin');
            });

        $action = new SendChatMessage($telegram);
        $action->execute($user, $community, 'Watch this', 'https://example.com/video.mp4', 'video');
    }

    public function test_does_not_send_telegram_when_not_configured(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'           => $user->id,
            'telegram_bot_token' => null,
            'telegram_chat_id'   => null,
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldNotReceive('sendMessage');
        $telegram->shouldNotReceive('sendPhoto');
        $telegram->shouldNotReceive('sendVideo');

        $action = new SendChatMessage($telegram);
        $action->execute($user, $community, 'No telegram');
    }

    public function test_telegram_prefix_shows_member_for_non_owner(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create(['name' => 'Dave']);
        $community = Community::factory()->create([
            'owner_id'           => $owner->id,
            'telegram_bot_token' => 'token',
            'telegram_chat_id'   => 'chat',
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($token, $chatId, $text) {
                return str_contains($text, 'Member') && str_contains($text, 'Dave');
            });

        $action = new SendChatMessage($telegram);
        $action->execute($member, $community, 'Member message');
    }

    public function test_sends_photo_with_empty_content(): void
    {
        $user      = User::factory()->create(['name' => 'Eve']);
        $community = Community::factory()->create([
            'owner_id'           => $user->id,
            'telegram_bot_token' => 'token',
            'telegram_chat_id'   => 'chat',
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendPhoto')
            ->once()
            ->withArgs(function ($token, $chatId, $photoUrl, $caption) {
                // When content is empty, caption should just be the prefix
                return str_contains($caption, 'Admin') && str_contains($caption, 'Eve');
            });

        $action = new SendChatMessage($telegram);
        $action->execute($user, $community, '', 'https://example.com/photo.jpg', 'image');
    }
}
