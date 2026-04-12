<?php

namespace Tests\Feature\Web;

use App\Ai\Agents\CommunityAssistant;
use App\Models\User;
use App\Queries\AI\BuildAIContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Image;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AIAssistantControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockContextWithCommunities(User $user): void
    {
        $this->mock(BuildAIContext::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('execute')
                ->andReturn([
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'communities' => [
                        ['name' => 'Test Community', 'role' => 'member', 'points' => 10, 'level' => 1, 'lessons_done' => 2, 'lessons_total' => 5, 'lessons_pending_names' => [], 'quizzes' => [], 'badges' => []],
                    ],
                ]);
        });
    }

    private function mockContextEmpty(): void
    {
        $this->mock(BuildAIContext::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->andReturn(['id' => 1, 'name' => 'Test', 'email' => 'test@test.com', 'communities' => []]);
        });
    }

    // ── greet ─────────────────────────────────────────────────────────────────

    public function test_greet_returns_greeting_for_user_with_communities(): void
    {
        CommunityAssistant::fake(['Hello! Welcome to Curzzo.']);

        $user = User::factory()->create();
        $this->mockContextWithCommunities($user);

        $this->actingAs($user)
            ->postJson(route('ai.greet'))
            ->assertOk()
            ->assertJsonStructure(['message', 'conversation_id']);
    }

    public function test_greet_returns_fallback_when_user_has_no_communities(): void
    {
        $user = User::factory()->create();
        $this->mockContextEmpty();

        $this->actingAs($user)
            ->postJson(route('ai.greet'))
            ->assertOk()
            ->assertJsonFragment(['conversation_id' => null])
            ->assertJsonPath('message', "Hi {$user->name}! Join a community to get started.");
    }

    public function test_greet_requires_auth(): void
    {
        $this->postJson(route('ai.greet'))->assertUnauthorized();
    }

    // ── chat ──────────────────────────────────────────────────────────────────

    public function test_chat_returns_response_for_user_with_communities(): void
    {
        CommunityAssistant::fake(['Here is your next step.']);

        $user = User::factory()->create();
        $this->mockContextWithCommunities($user);

        $this->actingAs($user)
            ->postJson(route('ai.chat'), [
                'message'         => 'What should I do next?',
                'conversation_id' => null,
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'conversation_id']);
    }

    public function test_chat_continues_existing_conversation(): void
    {
        CommunityAssistant::fake(['Sure, here is more info.']);

        $user = User::factory()->create();
        $this->mockContextWithCommunities($user);

        $this->actingAs($user)
            ->postJson(route('ai.chat'), [
                'message'         => 'Tell me more',
                'conversation_id' => 'b7a1c3d2-e456-4f78-9a0b-1c2d3e4f5a6b',
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'conversation_id']);
    }

    public function test_chat_returns_403_when_user_has_no_communities(): void
    {
        $user = User::factory()->create();
        $this->mockContextEmpty();

        $this->actingAs($user)
            ->postJson(route('ai.chat'), ['message' => 'Hello'])
            ->assertForbidden()
            ->assertJson(['error' => 'You must be a member of a community to use the AI assistant.']);
    }

    public function test_chat_validates_message_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('ai.chat'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_chat_validates_message_max_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('ai.chat'), ['message' => str_repeat('a', 1001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_chat_validates_conversation_id_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('ai.chat'), [
                'message'         => 'Hello',
                'conversation_id' => 'not-a-uuid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('conversation_id');
    }

    public function test_chat_requires_auth(): void
    {
        $this->postJson(route('ai.chat'), ['message' => 'Hello'])
            ->assertUnauthorized();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_chat_returns_image_when_message_requests_image_generation(): void
    {
        $user = User::factory()->create();
        $this->mockContextWithCommunities($user);

        $fakeImg       = new \stdClass();
        $fakeImg->mime  = 'image/png';
        $fakeImg->image = base64_encode('fake-image-data');

        $fakeResponse = Mockery::mock();
        $fakeResponse->shouldReceive('firstImage')->andReturn($fakeImg);

        $imageMock = Mockery::mock('alias:Laravel\Ai\Image');
        $imageMock->shouldReceive('of')->andReturnSelf();
        $imageMock->shouldReceive('size')->andReturnSelf();
        $imageMock->shouldReceive('generate')->andReturn($fakeResponse);

        $this->actingAs($user)
            ->postJson(route('ai.chat'), [
                'message' => 'Generate an image of a sunset',
            ])
            ->assertOk()
            ->assertJsonPath('type', 'image')
            ->assertJsonStructure(['type', 'message']);
    }

    public function test_chat_does_not_treat_normal_message_as_image_request(): void
    {
        CommunityAssistant::fake(['Normal response.']);

        $user = User::factory()->create();
        $this->mockContextWithCommunities($user);

        $this->actingAs($user)
            ->postJson(route('ai.chat'), [
                'message' => 'What is my progress?',
            ])
            ->assertOk()
            ->assertJsonPath('type', 'text');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_chat_returns_friendly_error_when_image_generation_fails(): void
    {
        $user = User::factory()->create();
        $this->mockContextWithCommunities($user);

        $imageMock = Mockery::mock('alias:Laravel\Ai\Image');
        $imageMock->shouldReceive('of')->andReturnSelf();
        $imageMock->shouldReceive('size')->andReturnSelf();
        $imageMock->shouldReceive('generate')->andThrow(new \RuntimeException('AI offline'));

        \Illuminate\Support\Facades\Log::shouldReceive('error')->atLeast()->once();

        $this->actingAs($user)
            ->postJson(route('ai.chat'), [
                'message' => 'generate an image of a sunset',
            ])
            ->assertOk()
            ->assertJsonPath('type', 'text')
            ->assertJsonPath('message', "Sorry, I couldn't generate that image right now. Please try again later.");
    }
}
