<?php

namespace Tests\Feature\Api;

use App\Ai\Agents\CommunityAssistant;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Queries\AI\BuildAIContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Ai\Image;
use Mockery;
use Tests\TestCase;

class AIAssistantControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fullContext(User $user, Community $community): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'communities' => [[
                'name'                  => $community->name,
                'role'                  => 'member',
                'points'                => 0,
                'level'                 => 1,
                'lessons_done'          => 0,
                'lessons_total'         => 0,
                'lessons_pending_names' => [],
                'quizzes'               => [],
                'badges'                => [],
            ]],
        ];
    }

    // ─── greet ────────────────────────────────────────────────────────────────

    public function test_greet_requires_authentication(): void
    {
        $this->postJson('/api/ai/greet')
            ->assertUnauthorized();
    }

    public function test_greet_returns_fallback_when_user_has_no_communities(): void
    {
        $user = User::factory()->create(['name' => 'Jane']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/greet')
            ->assertOk()
            ->assertJsonPath('message', 'Hi Jane! Join a community to get started.')
            ->assertJsonPath('conversation_id', null);
    }

    public function test_greet_returns_ai_response_when_user_has_communities(): void
    {
        Ai::fakeAgent(CommunityAssistant::class, ['Welcome back!']);

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $mockContext = Mockery::mock(BuildAIContext::class);
        $mockContext->shouldReceive('execute')->once()->andReturn($this->fullContext($user, $community));
        $this->instance(BuildAIContext::class, $mockContext);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/greet')
            ->assertOk()
            ->assertJsonStructure(['message', 'conversation_id']);
    }

    // ─── chat ─────────────────────────────────────────────────────────────────

    public function test_chat_requires_authentication(): void
    {
        $this->postJson('/api/ai/chat', ['message' => 'hello'])
            ->assertUnauthorized();
    }

    public function test_chat_validates_message_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/chat', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_chat_validates_message_max_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/chat', ['message' => str_repeat('a', 1001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_chat_returns_403_when_user_has_no_communities(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/chat', ['message' => 'Hello'])
            ->assertForbidden()
            ->assertJsonPath('error', 'You must be a member of a community to use the AI assistant.');
    }

    public function test_chat_returns_ai_response_for_new_conversation(): void
    {
        Ai::fakeAgent(CommunityAssistant::class, ['Here is some help.']);

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $mockContext = Mockery::mock(BuildAIContext::class);
        $mockContext->shouldReceive('execute')->once()->andReturn($this->fullContext($user, $community));
        $this->instance(BuildAIContext::class, $mockContext);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/chat', ['message' => 'What should I do next?'])
            ->assertOk()
            ->assertJsonStructure(['message', 'conversation_id']);
    }

    public function test_chat_continues_existing_conversation(): void
    {
        Ai::fakeAgent(CommunityAssistant::class, ['Continued response.']);

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $conversationId = fake()->uuid();

        $mockContext = Mockery::mock(BuildAIContext::class);
        $mockContext->shouldReceive('execute')->once()->andReturn($this->fullContext($user, $community));
        $this->instance(BuildAIContext::class, $mockContext);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/chat', [
                'message'         => 'Tell me more',
                'conversation_id' => $conversationId,
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'conversation_id']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_chat_returns_image_when_message_requests_image_generation(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $mockContext = Mockery::mock(BuildAIContext::class);
        $mockContext->shouldReceive('execute')->once()->andReturn($this->fullContext($user, $community));
        $this->instance(BuildAIContext::class, $mockContext);

        $fakeImg       = new \stdClass();
        $fakeImg->mime  = 'image/png';
        $fakeImg->image = base64_encode('fake-image-data');

        $fakeResponse = Mockery::mock();
        $fakeResponse->shouldReceive('firstImage')->andReturn($fakeImg);

        $imageMock = Mockery::mock('alias:Laravel\Ai\Image');
        $imageMock->shouldReceive('of')->andReturnSelf();
        $imageMock->shouldReceive('size')->andReturnSelf();
        $imageMock->shouldReceive('generate')->andReturn($fakeResponse);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/chat', ['message' => 'Generate an image of a cat'])
            ->assertOk()
            ->assertJsonPath('type', 'image')
            ->assertJsonStructure(['type', 'message']);
    }

    public function test_chat_does_not_treat_normal_message_as_image_request(): void
    {
        Ai::fakeAgent(CommunityAssistant::class, ['Normal response.']);

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $mockContext = Mockery::mock(BuildAIContext::class);
        $mockContext->shouldReceive('execute')->once()->andReturn($this->fullContext($user, $community));
        $this->instance(BuildAIContext::class, $mockContext);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ai/chat', [
                'message' => 'What lessons should I take next?',
            ])
            ->assertOk()
            ->assertJsonPath('type', 'text');
    }
}
