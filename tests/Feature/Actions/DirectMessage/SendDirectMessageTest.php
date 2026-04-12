<?php

namespace Tests\Feature\Actions\DirectMessage;

use App\Actions\DirectMessage\SendDirectMessage;
use App\Events\DirectMessageSent;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SendDirectMessageTest extends TestCase
{
    use RefreshDatabase;

    private SendDirectMessage $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new SendDirectMessage();
    }

    public function test_message_is_persisted(): void
    {
        Event::fake([DirectMessageSent::class]);

        $sender   = User::factory()->create();
        $receiver = User::factory()->create();

        $message = $this->action->execute($sender, $receiver, 'Hello there!');

        $this->assertInstanceOf(DirectMessage::class, $message);
        $this->assertDatabaseHas('direct_messages', [
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'content'     => 'Hello there!',
        ]);
    }

    public function test_event_is_dispatched(): void
    {
        Event::fake([DirectMessageSent::class]);

        $sender   = User::factory()->create();
        $receiver = User::factory()->create();

        $this->action->execute($sender, $receiver, 'Hi!');

        Event::assertDispatched(DirectMessageSent::class, function ($event) use ($sender, $receiver) {
            return $event->senderId === $sender->id && $event->receiverId === $receiver->id;
        });
    }

    public function test_returned_message_has_correct_content(): void
    {
        Event::fake([DirectMessageSent::class]);

        $sender   = User::factory()->create();
        $receiver = User::factory()->create();
        $content  = 'Test message content';

        $message = $this->action->execute($sender, $receiver, $content);

        $this->assertSame($content, $message->content);
        $this->assertSame($sender->id, $message->sender_id);
        $this->assertSame($receiver->id, $message->receiver_id);
    }
}
