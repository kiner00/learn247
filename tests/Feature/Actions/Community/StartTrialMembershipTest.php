<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\JoinCommunity;
use App\Actions\Community\StartTrialMembership;
use App\Events\MemberJoined;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StartTrialMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function action(): StartTrialMembership
    {
        return new StartTrialMembership(new JoinCommunity);
    }

    public function test_creates_free_member_with_expires_at_for_per_user_trial(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create([
            'trial_mode' => Community::TRIAL_PER_USER,
            'trial_days' => 7,
        ]);

        $this->action()->execute($user, $community);

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertSame(CommunityMember::MEMBERSHIP_FREE, $member->membership_type);
        $this->assertNotNull($member->expires_at);
        $this->assertEqualsWithDelta(
            now()->addDays(7)->timestamp,
            $member->expires_at->timestamp,
            5,
        );
        Event::assertDispatched(MemberJoined::class);
    }

    public function test_creates_free_member_with_free_until_for_window_trial(): void
    {
        $freeUntil = now()->addDays(30)->endOfDay();
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create([
            'trial_mode' => Community::TRIAL_WINDOW,
            'free_until' => $freeUntil,
        ]);

        $this->action()->execute($user, $community);

        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertSame(CommunityMember::MEMBERSHIP_FREE, $member->membership_type);
        $this->assertEqualsWithDelta($freeUntil->timestamp, $member->expires_at->timestamp, 1);
    }

    public function test_throws_when_community_has_no_trial_configured(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create(['trial_mode' => Community::TRIAL_NONE]);

        $this->expectException(ValidationException::class);
        $this->action()->execute($user, $community);
    }

    public function test_throws_when_window_trial_has_past_date(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create([
            'trial_mode' => Community::TRIAL_WINDOW,
            'free_until' => now()->subDay(),
        ]);

        $this->expectException(ValidationException::class);
        $this->action()->execute($user, $community);
    }

    public function test_throws_when_already_a_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create([
            'trial_mode' => Community::TRIAL_PER_USER,
            'trial_days' => 7,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->action()->execute($user, $community);
    }
}
