<?php

namespace Tests\Feature\Actions\Curzzo;

use App\Actions\Curzzo\ChatResult;
use App\Actions\Curzzo\ChatWithCurzzo;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Curzzo;
use App\Models\CurzzoMessage;
use App\Models\CurzzoTopup;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Community\CurzzoAccessService;
use App\Services\Community\CurzzoLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ChatWithCurzzoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Subclass that lets us inject a fake agent so the unit test never
     * touches the real LLM and we can simulate failure paths.
     */
    private function actionWithAgent(object $agent): ChatWithCurzzo
    {
        return new class(app(CurzzoLimitService::class), app(CurzzoAccessService::class), $agent) extends ChatWithCurzzo
        {
            public function __construct(CurzzoLimitService $limits, CurzzoAccessService $access, private object $stubAgent)
            {
                parent::__construct($limits, $access);
            }

            protected function makeAgent(Curzzo $curzzo, Community $community): object
            {
                return $this->stubAgent;
            }
        };
    }

    public function test_returns_503_when_agent_throws(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $user = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $agent = new class
        {
            public function forUser($user): self
            {
                return $this;
            }

            public function continue($id, $as): self
            {
                return $this;
            }

            public function prompt($message)
            {
                throw new RuntimeException('LLM down');
            }
        };

        $result = $this->actionWithAgent($agent)->execute(
            $user,
            $community,
            $curzzo,
            'hi',
            conversationId: 'abc-123',
        );

        $this->assertInstanceOf(ChatResult::class, $result);
        $this->assertSame(503, $result->status);
        $this->assertSame('The bot had trouble responding. Please try again.', $result->body['error']);
        $this->assertSame('abc-123', $result->body['conversation_id']);

        // No messages persisted on failure
        $this->assertDatabaseMissing('curzzo_messages', ['user_id' => $user->id]);
    }

    public function test_allows_inclusive_curzzo_for_paid_subscriber(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'access_type' => 'inclusive',
            'price' => 49.00,
            'currency' => 'PHP',
            'billing_type' => 'monthly',
        ]);

        $user = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_PAID,
        ]);
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $agent = new class
        {
            public function forUser($user): self
            {
                return $this;
            }

            public function continue($id, $as): self
            {
                return $this;
            }

            public function prompt($message): object
            {
                return new class
                {
                    public string $text = 'hi back';

                    public string $conversationId = 'conv-inc';
                };
            }
        };

        $result = $this->actionWithAgent($agent)->execute($user, $community, $curzzo, 'hello');

        $this->assertSame(200, $result->status);
        $this->assertSame('hi back', $result->body['message']);
    }

    public function test_returns_403_when_paid_curzzo_not_purchased(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->paidOnce()->create(['community_id' => $community->id]);

        $user = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $agent = new class
        {
            public function forUser($user): self
            {
                return $this;
            }

            public function continue($id, $as): self
            {
                return $this;
            }

            public function prompt($message): object
            {
                throw new RuntimeException('should not be called');
            }
        };

        $result = $this->actionWithAgent($agent)->execute($user, $community, $curzzo, 'hello');

        $this->assertSame(403, $result->status);
        $this->assertSame('Purchase required to chat with this Curzzo.', $result->body['error']);
    }

    public function test_consumes_topup_when_using_topup(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);
        $user = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        // Hit the free daily limit so the next message must use a topup
        for ($i = 0; $i < 10; $i++) {
            CurzzoMessage::create([
                'curzzo_id' => $curzzo->id,
                'community_id' => $community->id,
                'user_id' => $user->id,
                'role' => 'user',
                'content' => "m$i",
            ]);
        }

        $topup = CurzzoTopup::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'status' => CurzzoTopup::STATUS_PAID,
            'messages' => 10,
            'messages_used' => 0,
            'expires_at' => null,
        ]);

        $agent = new class
        {
            public function forUser($user): self
            {
                return $this;
            }

            public function continue($id, $as): self
            {
                return $this;
            }

            public function prompt($message): object
            {
                return new class
                {
                    public string $text = 'response';

                    public string $conversationId = 'conv-1';
                };
            }
        };

        $result = $this->actionWithAgent($agent)->execute(
            $user,
            $community,
            $curzzo,
            'one more',
        );

        $this->assertSame(200, $result->status);
        $this->assertSame(1, $topup->fresh()->messages_used);
    }
}
