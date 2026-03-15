<?php

namespace Tests\Feature\Actions\Chat;

use App\Actions\Chat\DeleteChatMessage;
use App\Models\Community;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteChatMessageTest extends TestCase
{
    use RefreshDatabase;

    private DeleteChatMessage $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new DeleteChatMessage();
    }

    public function test_owner_can_delete_own_message(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $message   = Message::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'content'      => 'Hello',
        ]);

        $this->action->execute($user, $community, $message);

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_cannot_delete_another_users_message(): void
    {
        $owner     = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->create();
        $message   = Message::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'content'      => 'Hello',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You can only delete your own messages.');
        $this->action->execute($otherUser, $community, $message);
    }

    public function test_message_from_wrong_community_throws_exception(): void
    {
        $user       = User::factory()->create();
        $community1  = Community::factory()->create();
        $community2  = Community::factory()->create();
        $message    = Message::create([
            'community_id' => $community1->id,
            'user_id'      => $user->id,
            'content'      => 'Hello',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Message does not belong to this community.');
        $this->action->execute($user, $community2, $message);
    }
}
