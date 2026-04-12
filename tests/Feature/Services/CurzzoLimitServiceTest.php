<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Curzzo;
use App\Models\CurzzoMessage;
use App\Models\User;
use App\Services\Community\CurzzoLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurzzoLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private CurzzoLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CurzzoLimitService();
    }

    public function test_owner_has_unlimited_daily_limit(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->assertSame(PHP_INT_MAX, $this->service->dailyLimit($owner, $community));
    }

    public function test_free_member_gets_base_daily_limit(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $this->assertSame(10, $this->service->dailyLimit($user, $community));
    }

    public function test_paid_member_gets_higher_daily_limit(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_PAID,
        ]);

        $this->assertSame(50, $this->service->dailyLimit($user, $community));
    }

    public function test_today_usage_counts_only_today_user_messages(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo    = Curzzo::create([
            'community_id' => $community->id,
            'name'         => 'Test Bot',
            'instructions' => 'Be helpful.',
        ]);

        // Today's user message
        CurzzoMessage::create([
            'curzzo_id'     => $curzzo->id,
            'community_id'  => $community->id,
            'user_id'       => $user->id,
            'role'          => 'user',
            'content'       => 'hello',
        ]);

        // Today's assistant message (should not count)
        CurzzoMessage::create([
            'curzzo_id'     => $curzzo->id,
            'community_id'  => $community->id,
            'user_id'       => $user->id,
            'role'          => 'assistant',
            'content'       => 'hi',
        ]);

        $this->assertSame(1, $this->service->todayUsage($user, $community));
    }

    public function test_owner_is_always_allowed_to_send(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $result = $this->service->canSendMessage($owner, $community);

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    public function test_get_packs_returns_defaults_when_community_has_none(): void
    {
        $community = Community::factory()->create();

        $packs = $this->service->getPacks($community);

        $this->assertSame(CurzzoLimitService::DEFAULT_PACKS, $packs);
    }
}
