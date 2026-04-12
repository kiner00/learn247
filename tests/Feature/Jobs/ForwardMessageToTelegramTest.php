<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ForwardMessageToTelegram;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ForwardMessageToTelegramTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_text_message_when_no_media(): void
    {
        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->with('bot-token', '12345', 'Hello world');

        $job = new ForwardMessageToTelegram('bot-token', '12345', 'Hello world');
        $job->handle($telegram);
    }

    public function test_sends_photo_when_media_type_is_image(): void
    {
        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendPhoto')
            ->once()
            ->with('bot-token', '12345', 'https://example.com/photo.jpg', 'A photo');

        $job = new ForwardMessageToTelegram('bot-token', '12345', 'A photo', 'https://example.com/photo.jpg', 'image');
        $job->handle($telegram);
    }

    public function test_sends_video_when_media_type_is_video(): void
    {
        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendVideo')
            ->once()
            ->with('bot-token', '12345', 'https://example.com/video.mp4', 'A video');

        $job = new ForwardMessageToTelegram('bot-token', '12345', 'A video', 'https://example.com/video.mp4', 'video');
        $job->handle($telegram);
    }

    public function test_sends_text_message_when_media_url_present_but_no_type(): void
    {
        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->with('bot-token', '12345', 'Fallback text');

        $job = new ForwardMessageToTelegram('bot-token', '12345', 'Fallback text', 'https://example.com/file.pdf', null);
        $job->handle($telegram);
    }
}
