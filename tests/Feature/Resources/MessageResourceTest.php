<?php

namespace Tests\Feature\Resources;

use App\Http\Resources\MessageResource;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MessageResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_resource_returns_expected_keys(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $message = DirectMessage::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'content' => 'Hello there',
        ]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $sender);

        $resource = (new MessageResource($message))->toArray($request);

        $this->assertArrayHasKey('id', $resource);
        $this->assertArrayHasKey('content', $resource);
        $this->assertArrayHasKey('is_mine', $resource);
        $this->assertArrayHasKey('read_at', $resource);
        $this->assertArrayHasKey('created_at', $resource);
    }

    public function test_message_resource_is_mine_true_for_sender(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $message = DirectMessage::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'content' => 'My message',
        ]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $sender);

        $resource = (new MessageResource($message))->toArray($request);

        $this->assertTrue($resource['is_mine']);
        $this->assertSame('My message', $resource['content']);
    }

    public function test_message_resource_is_mine_false_for_receiver(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $message = DirectMessage::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'content' => 'Not mine',
        ]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $receiver);

        $resource = (new MessageResource($message))->toArray($request);

        $this->assertFalse($resource['is_mine']);
    }

    public function test_message_resource_is_mine_false_for_guest(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $message = DirectMessage::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'content' => 'Guest view',
        ]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => null);

        $resource = (new MessageResource($message))->toArray($request);

        $this->assertFalse($resource['is_mine']);
    }
}
