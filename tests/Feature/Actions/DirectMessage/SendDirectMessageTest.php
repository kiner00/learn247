<?php

namespace Tests\Feature\Actions\DirectMessage;

use App\Actions\DirectMessage\SendDirectMessage;
use App\Events\DirectMessageSent;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SendDirectMessageTest extends TestCase
{
    use RefreshDatabase;

    private SendDirectMessage $action;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->action = app(SendDirectMessage::class);
    }

    public function test_message_is_persisted(): void
    {
        Event::fake([DirectMessageSent::class]);

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $message = $this->action->execute($sender, $receiver, 'Hello there!');

        $this->assertInstanceOf(DirectMessage::class, $message);
        $this->assertDatabaseHas('direct_messages', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'content' => 'Hello there!',
        ]);
    }

    public function test_event_is_dispatched(): void
    {
        Event::fake([DirectMessageSent::class]);

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $this->action->execute($sender, $receiver, 'Hi!');

        Event::assertDispatched(DirectMessageSent::class, function ($event) use ($sender, $receiver) {
            return $event->senderId === $sender->id && $event->receiverId === $receiver->id;
        });
    }

    public function test_returned_message_has_correct_content(): void
    {
        Event::fake([DirectMessageSent::class]);

        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $content = 'Test message content';

        $message = $this->action->execute($sender, $receiver, $content);

        $this->assertSame($content, $message->content);
        $this->assertSame($sender->id, $message->sender_id);
        $this->assertSame($receiver->id, $message->receiver_id);
    }

    public function test_can_send_image_only_message(): void
    {
        Event::fake([DirectMessageSent::class]);

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $message = $this->action->execute($sender, $receiver, null, UploadedFile::fake()->image('pic.png'));

        $this->assertNull($message->content);
        $this->assertNotNull($message->image_url);
        $this->assertCount(1, Storage::allFiles('direct-message-attachments/'.$sender->id));
    }

    public function test_can_send_text_plus_image(): void
    {
        Event::fake([DirectMessageSent::class]);

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $message = $this->action->execute($sender, $receiver, 'caption', UploadedFile::fake()->image('pic.png'));

        $this->assertSame('caption', $message->content);
        $this->assertNotNull($message->image_url);
    }

    public function test_rejects_empty_message(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute($sender, $receiver, '   ', null);
    }

    public function test_event_payload_includes_image_url(): void
    {
        Event::fake([DirectMessageSent::class]);

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $this->action->execute($sender, $receiver, null, UploadedFile::fake()->image('pic.png'));

        Event::assertDispatched(DirectMessageSent::class, function ($event) {
            return ! empty($event->message['image_url']);
        });
    }
}
