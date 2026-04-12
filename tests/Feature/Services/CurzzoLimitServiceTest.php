<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Curzzo;
use App\Models\CurzzoMessage;
use App\Models\CurzzoPurchase;
use App\Models\CurzzoTopup;
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

    // ─── dailyLimit: buyer with active curzzo purchase ───────────────────────

    public function test_buyer_with_active_curzzo_purchase_gets_buyer_limit(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo    = Curzzo::create([
            'community_id' => $community->id,
            'name'         => 'Test Bot',
            'instructions' => 'Be helpful.',
        ]);
        CurzzoPurchase::create([
            'user_id'   => $user->id,
            'curzzo_id' => $curzzo->id,
            'status'    => CurzzoPurchase::STATUS_PAID,
            'expires_at' => null, // lifetime
        ]);

        $this->assertSame(100, $this->service->dailyLimit($user, $community));
    }

    // ─── topupRemaining ──────────────────────────────────────────────────────

    public function test_topup_remaining_returns_zero_when_no_topups(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        $this->assertSame(0, $this->service->topupRemaining($user, $community));
    }

    public function test_topup_remaining_sums_active_message_packs(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PAID,
            'messages'      => 50,
            'messages_used' => 10,
            'expires_at'    => null,
        ]);
        CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PAID,
            'messages'      => 200,
            'messages_used' => 0,
            'expires_at'    => null,
        ]);

        $this->assertSame(240, $this->service->topupRemaining($user, $community));
    }

    public function test_topup_remaining_returns_max_int_for_active_day_pass(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PAID,
            'messages'      => 0,
            'messages_used' => 0,
            'expires_at'    => now()->addHours(12),
        ]);

        $this->assertSame(PHP_INT_MAX, $this->service->topupRemaining($user, $community));
    }

    public function test_topup_remaining_ignores_expired_day_pass(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PAID,
            'messages'      => 0,
            'messages_used' => 0,
            'expires_at'    => now()->subHour(),
        ]);

        $this->assertSame(0, $this->service->topupRemaining($user, $community));
    }

    public function test_topup_remaining_ignores_pending_topups(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PENDING,
            'messages'      => 50,
            'messages_used' => 0,
            'expires_at'    => null,
        ]);

        $this->assertSame(0, $this->service->topupRemaining($user, $community));
    }

    // ─── canSendMessage edge cases ───────────────────────────────────────────

    public function test_can_send_message_allowed_when_under_daily_limit(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $result = $this->service->canSendMessage($user, $community);

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    public function test_can_send_message_denied_when_over_daily_limit_no_topup(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $curzzo = Curzzo::create([
            'community_id' => $community->id,
            'name'         => 'Bot',
            'instructions' => 'Help',
        ]);

        // Create 10 messages to hit free limit
        for ($i = 0; $i < 10; $i++) {
            CurzzoMessage::create([
                'curzzo_id'     => $curzzo->id,
                'community_id'  => $community->id,
                'user_id'       => $user->id,
                'role'          => 'user',
                'content'       => "msg {$i}",
            ]);
        }

        $result = $this->service->canSendMessage($user, $community);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('daily limit', $result['reason']);
    }

    public function test_can_send_message_allowed_with_topup_when_over_daily_limit(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $curzzo = Curzzo::create([
            'community_id' => $community->id,
            'name'         => 'Bot',
            'instructions' => 'Help',
        ]);

        for ($i = 0; $i < 10; $i++) {
            CurzzoMessage::create([
                'curzzo_id'     => $curzzo->id,
                'community_id'  => $community->id,
                'user_id'       => $user->id,
                'role'          => 'user',
                'content'       => "msg {$i}",
            ]);
        }

        // Active topup
        CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PAID,
            'messages'      => 50,
            'messages_used' => 0,
            'expires_at'    => null,
        ]);

        $result = $this->service->canSendMessage($user, $community);

        $this->assertTrue($result['allowed']);
        $this->assertTrue($result['using_topup'] ?? false);
    }

    // ─── consumeTopup ────────────────────────────────────────────────────────

    public function test_consume_topup_increments_messages_used(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        $topup = CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PAID,
            'messages'      => 50,
            'messages_used' => 5,
            'expires_at'    => null,
        ]);

        $this->service->consumeTopup($user, $community);

        $topup->refresh();
        $this->assertSame(6, $topup->messages_used);
    }

    public function test_consume_topup_does_not_increment_day_pass(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        $topup = CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PAID,
            'messages'      => 0,
            'messages_used' => 0,
            'expires_at'    => now()->addHours(12),
        ]);

        $this->service->consumeTopup($user, $community);

        $topup->refresh();
        $this->assertSame(0, $topup->messages_used);
    }

    public function test_consume_topup_uses_oldest_active_topup_first(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        $older = CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PAID,
            'messages'      => 50,
            'messages_used' => 0,
            'expires_at'    => null,
            'created_at'    => now()->subDay(),
        ]);
        $newer = CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PAID,
            'messages'      => 50,
            'messages_used' => 0,
            'expires_at'    => null,
            'created_at'    => now(),
        ]);

        $this->service->consumeTopup($user, $community);

        $older->refresh();
        $newer->refresh();
        $this->assertSame(1, $older->messages_used);
        $this->assertSame(0, $newer->messages_used);
    }

    // ─── communityMonthlyUsage ───────────────────────────────────────────────

    public function test_community_monthly_usage_counts_only_this_month(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo    = Curzzo::create([
            'community_id' => $community->id,
            'name'         => 'Bot',
            'instructions' => 'Help',
        ]);

        // This month
        CurzzoMessage::create([
            'curzzo_id'     => $curzzo->id,
            'community_id'  => $community->id,
            'user_id'       => $user->id,
            'role'          => 'user',
            'content'       => 'hello',
        ]);

        // Last month — backdate via query to avoid Eloquent timestamp override
        $oldMsg = CurzzoMessage::create([
            'curzzo_id'     => $curzzo->id,
            'community_id'  => $community->id,
            'user_id'       => $user->id,
            'role'          => 'user',
            'content'       => 'old msg',
        ]);
        CurzzoMessage::where('id', $oldMsg->id)->update([
            'created_at' => now()->subMonth()->subDay(),
        ]);

        $this->assertSame(1, $this->service->communityMonthlyUsage($community));
    }

    // ─── getPacks with custom packs ──────────────────────────────────────────

    public function test_can_send_message_denied_when_community_monthly_cap_hit(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        // Stub service with the monthly cap already hit
        $service = new class extends CurzzoLimitService {
            public function communityMonthlyUsage(\App\Models\Community $community): int
            {
                return 10000;
            }
        };

        $result = $service->canSendMessage($user, $community);

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('monthly', $result['reason']);
    }

    public function test_consume_topup_does_nothing_when_no_active_topup(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        // Expired message pack (no remaining capacity)
        CurzzoTopup::create([
            'user_id'       => $user->id,
            'community_id'  => $community->id,
            'status'        => CurzzoTopup::STATUS_PAID,
            'messages'      => 5,
            'messages_used' => 5,
            'expires_at'    => null,
        ]);

        // Should not throw
        $this->service->consumeTopup($user, $community);

        $this->assertTrue(true);
    }

    public function test_get_packs_returns_custom_packs_when_set(): void
    {
        $customPacks = [
            ['messages' => 100, 'price' => 99, 'label' => '100 Messages'],
        ];
        $community = Community::factory()->create(['curzzo_topup_packs' => $customPacks]);

        $packs = $this->service->getPacks($community);

        $this->assertSame($customPacks, $packs);
    }
}
