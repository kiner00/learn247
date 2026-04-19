<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\Message;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private Community $community;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->community = Community::factory()->create([
            'telegram_bot_token' => 'test-bot-token',
            'telegram_chat_id' => '-1001234567890',
        ]);

        $this->secret = (new TelegramService)->webhookSecret('test-bot-token');
    }

    public function test_returns_200_for_unknown_slug(): void
    {
        $response = $this->postJson('/webhooks/telegram/nonexistent-slug', []);

        $response->assertStatus(200);
    }

    public function test_returns_200_when_community_has_no_bot_token(): void
    {
        $community = Community::factory()->create([
            'telegram_bot_token' => null,
            'telegram_chat_id' => '-100999',
        ]);

        $response = $this->postJson('/webhooks/telegram/'.$community->slug, []);

        $response->assertStatus(200);
    }

    public function test_returns_200_when_secret_does_not_match(): void
    {
        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            ['message' => ['chat' => ['id' => '-1001234567890'], 'text' => 'Hello']],
            ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret']
        );

        $response->assertStatus(200);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_creates_message_from_text_message(): void
    {
        Http::fake(); // No external calls expected for text-only messages

        $payload = [
            'message' => [
                'chat' => ['id' => -1001234567890],
                'from' => [
                    'id' => 111,
                    'is_bot' => false,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                ],
                'text' => 'Hello from Telegram!',
            ],
        ];

        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'community_id' => $this->community->id,
            'content' => 'Hello from Telegram!',
            'telegram_author' => 'John Doe',
        ]);
    }

    public function test_creates_message_from_photo_with_caption(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'photos/file_99.jpg'],
            ], 200),
        ]);

        $payload = [
            'message' => [
                'chat' => ['id' => -1001234567890],
                'from' => [
                    'id' => 222,
                    'is_bot' => false,
                    'first_name' => 'Jane',
                ],
                'caption' => 'My photo caption',
                'photo' => [
                    ['file_id' => 'small_id', 'width' => 90, 'height' => 90],
                    ['file_id' => 'large_id', 'width' => 800, 'height' => 600],
                ],
            ],
        ];

        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'community_id' => $this->community->id,
            'content' => 'My photo caption',
            'telegram_author' => 'Jane',
            'media_type' => 'image',
        ]);
    }

    public function test_creates_message_from_video(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'videos/file_55.mp4'],
            ], 200),
        ]);

        $payload = [
            'message' => [
                'chat' => ['id' => -1001234567890],
                'from' => [
                    'id' => 333,
                    'is_bot' => false,
                    'username' => 'videoguy',
                ],
                'caption' => 'Check this video',
                'video' => ['file_id' => 'vid_file_id', 'duration' => 30],
            ],
        ];

        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'community_id' => $this->community->id,
            'telegram_author' => 'videoguy',
            'media_type' => 'video',
        ]);
    }

    public function test_skips_bot_messages(): void
    {
        Http::fake();

        $payload = [
            'message' => [
                'chat' => ['id' => -1001234567890],
                'from' => [
                    'id' => 444,
                    'is_bot' => true,
                ],
                'text' => 'Bot message',
            ],
        ];

        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertStatus(200);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_skips_messages_from_wrong_chat(): void
    {
        Http::fake();

        $payload = [
            'message' => [
                'chat' => ['id' => -999999],
                'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'Hacker'],
                'text' => 'Wrong chat',
            ],
        ];

        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertStatus(200);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_skips_update_without_message(): void
    {
        Http::fake();

        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            ['update_id' => 12345],
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertStatus(200);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_handles_channel_post(): void
    {
        Http::fake();

        $payload = [
            'channel_post' => [
                'chat' => ['id' => -1001234567890],
                'from' => [
                    'id' => 666,
                    'is_bot' => false,
                    'first_name' => 'Channel',
                    'last_name' => 'Admin',
                ],
                'text' => 'Channel announcement',
            ],
        ];

        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'content' => 'Channel announcement',
            'telegram_author' => 'Channel Admin',
        ]);
    }

    public function test_uses_username_when_name_is_empty(): void
    {
        Http::fake();

        $payload = [
            'message' => [
                'chat' => ['id' => -1001234567890],
                'from' => [
                    'id' => 777,
                    'is_bot' => false,
                    'username' => 'cooluser',
                ],
                'text' => 'No name user',
            ],
        ];

        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('messages', [
            'telegram_author' => 'cooluser',
        ]);
    }

    public function test_deduplicates_already_processed_messages(): void
    {
        Http::fake();

        // Pre-create a message with the same telegram_message_id
        Message::create([
            'community_id' => $this->community->id,
            'user_id' => $this->community->owner_id,
            'content' => 'Original',
            'telegram_author' => 'Someone',
            'telegram_message_id' => 9999,
        ]);

        $payload = [
            'message' => [
                'message_id' => 9999,
                'chat' => ['id' => -1001234567890],
                'from' => ['id' => 101, 'is_bot' => false, 'first_name' => 'Dup'],
                'text' => 'Duplicate attempt',
            ],
        ];

        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertStatus(200);
        // Still only 1 message — dedup worked
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_skips_message_with_no_text_and_no_media(): void
    {
        Http::fake();

        $payload = [
            'message' => [
                'chat' => ['id' => -1001234567890],
                'from' => ['id' => 888, 'is_bot' => false, 'first_name' => 'Sticker'],
                // No text, no photo, no video — e.g. a sticker
                'sticker' => ['file_id' => 'sticker_id'],
            ],
        ];

        $response = $this->postJson(
            '/webhooks/telegram/'.$this->community->slug,
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret]
        );

        $response->assertStatus(200);
        $this->assertDatabaseCount('messages', 0);
    }
}
