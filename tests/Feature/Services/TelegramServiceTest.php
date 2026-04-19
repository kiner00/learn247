<?php

namespace Tests\Feature\Services;

use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    use RefreshDatabase;

    private TelegramService $service;

    private string $token = 'test-bot-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TelegramService;
    }

    // ─── sendMessage ─────────────────────────────────────────────────────────

    public function test_send_message_posts_to_telegram_api(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        $this->service->sendMessage($this->token, '123456', '<b>Hello</b>');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && $request['chat_id'] === '123456'
                && $request['text'] === '<b>Hello</b>'
                && $request['parse_mode'] === 'HTML';
        });
    }

    public function test_send_message_does_not_throw_on_failure(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => false], 500),
        ]);

        // Should not throw
        $this->service->sendMessage($this->token, '123456', 'Hello');

        $this->assertTrue(true);
    }

    public function test_send_message_catches_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        $this->service->sendMessage($this->token, '123456', 'Hello');

        $this->assertTrue(true);
    }

    // ─── setWebhook ──────────────────────────────────────────────────────────

    public function test_set_webhook_returns_true_on_success(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        $result = $this->service->setWebhook($this->token, 'https://example.com/webhook', 'secret123');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/setWebhook')
                && $request['url'] === 'https://example.com/webhook'
                && $request['secret_token'] === 'secret123'
                && $request['allowed_updates'] === ['message'];
        });
    }

    public function test_set_webhook_returns_false_on_failure(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => false, 'description' => 'Bad token'], 401),
        ]);

        $result = $this->service->setWebhook($this->token, 'https://example.com/webhook', 'secret');

        $this->assertFalse($result);
    }

    public function test_set_webhook_returns_false_on_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Connection timeout');
        });

        $result = $this->service->setWebhook($this->token, 'https://example.com/webhook', 'secret');

        $this->assertFalse($result);
    }

    // ─── deleteWebhook ───────────────────────────────────────────────────────

    public function test_delete_webhook_posts_to_telegram_api(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        $this->service->deleteWebhook($this->token);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/deleteWebhook');
        });
    }

    public function test_delete_webhook_does_not_throw_on_failure(): void
    {
        Http::fake(function () {
            throw new \Exception('Timeout');
        });

        $this->service->deleteWebhook($this->token);

        $this->assertTrue(true);
    }

    // ─── sendPhoto ───────────────────────────────────────────────────────────

    public function test_send_photo_posts_to_telegram_api(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        $this->service->sendPhoto($this->token, '123456', 'https://img.com/photo.jpg', '<b>Caption</b>');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendPhoto')
                && $request['chat_id'] === '123456'
                && $request['photo'] === 'https://img.com/photo.jpg'
                && $request['caption'] === '<b>Caption</b>';
        });
    }

    public function test_send_photo_without_caption(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        $this->service->sendPhoto($this->token, '123456', 'https://img.com/photo.jpg');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendPhoto')
                && ! array_key_exists('caption', $request->data());
        });
    }

    public function test_send_photo_catches_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Timeout');
        });

        $this->service->sendPhoto($this->token, '123456', 'https://img.com/photo.jpg', 'Caption');

        $this->assertTrue(true);
    }

    // ─── sendVideo ───────────────────────────────────────────────────────────

    public function test_send_video_posts_to_telegram_api(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        $this->service->sendVideo($this->token, '123456', 'https://vid.com/video.mp4', 'My video');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendVideo')
                && $request['video'] === 'https://vid.com/video.mp4'
                && $request['caption'] === 'My video';
        });
    }

    public function test_send_video_without_caption(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response(['ok' => true], 200),
        ]);

        $this->service->sendVideo($this->token, '123456', 'https://vid.com/video.mp4');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendVideo')
                && ! array_key_exists('caption', $request->data());
        });
    }

    public function test_send_video_does_not_throw_on_failure(): void
    {
        Http::fake(function () {
            throw new \Exception('Timeout');
        });

        $this->service->sendVideo($this->token, '123456', 'https://vid.com/video.mp4');

        $this->assertTrue(true);
    }

    // ─── getFileUrl ──────────────────────────────────────────────────────────

    public function test_get_file_url_returns_full_url(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'photos/file_123.jpg'],
            ], 200),
        ]);

        $url = $this->service->getFileUrl($this->token, 'some-file-id');

        $this->assertEquals(
            'https://api.telegram.org/file/bot'.$this->token.'/photos/file_123.jpg',
            $url
        );
    }

    public function test_get_file_url_returns_null_when_no_file_path(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response([
                'ok' => true,
                'result' => [],
            ], 200),
        ]);

        $url = $this->service->getFileUrl($this->token, 'some-file-id');

        $this->assertNull($url);
    }

    public function test_get_file_url_returns_null_on_exception(): void
    {
        Http::fake(function () {
            throw new \Exception('Timeout');
        });

        $url = $this->service->getFileUrl($this->token, 'some-file-id');

        $this->assertNull($url);
    }

    // ─── getChatMemberCount ─────────────────────────────────────────────────

    public function test_get_chat_member_count_returns_count(): void
    {
        Http::fake([
            'https://api.telegram.org/bot*' => Http::response([
                'ok' => true,
                'result' => 42,
            ], 200),
        ]);

        $count = $this->service->getChatMemberCount($this->token, '123456');

        $this->assertEquals(42, $count);
    }

    public function test_get_chat_member_count_returns_null_on_failure(): void
    {
        Http::fake(function () {
            throw new \Exception('Timeout');
        });

        $count = $this->service->getChatMemberCount($this->token, '123456');

        $this->assertNull($count);
    }

    // ─── webhookSecret ───────────────────────────────────────────────────────

    public function test_webhook_secret_returns_deterministic_hash(): void
    {
        $secret1 = $this->service->webhookSecret('token-abc');
        $secret2 = $this->service->webhookSecret('token-abc');

        $this->assertEquals($secret1, $secret2);
        $this->assertEquals(32, strlen($secret1));
    }

    public function test_webhook_secret_differs_for_different_tokens(): void
    {
        $secret1 = $this->service->webhookSecret('token-abc');
        $secret2 = $this->service->webhookSecret('token-xyz');

        $this->assertNotEquals($secret1, $secret2);
    }
}
