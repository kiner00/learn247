<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityJoinTrialTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_creates_free_member_on_free_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->from("/communities/{$community->slug}")
            ->post("/communities/{$community->slug}/join")
            ->assertRedirect();

        $member = CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($member);
        $this->assertNull($member->expires_at);
    }

    public function test_join_creates_trial_member_on_paid_community_with_trial(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create([
            'trial_mode' => Community::TRIAL_PER_USER,
            'trial_days' => 7,
        ]);

        $this->actingAs($user)
            ->from("/communities/{$community->slug}")
            ->post("/communities/{$community->slug}/join")
            ->assertRedirect();

        $member = CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($member);
        $this->assertSame(CommunityMember::MEMBERSHIP_FREE, $member->membership_type);
        $this->assertNotNull($member->expires_at);
    }

    public function test_join_returns_error_for_paid_community_without_trial(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create(['trial_mode' => Community::TRIAL_NONE]);

        $response = $this->actingAs($user)
            ->from("/communities/{$community->slug}")
            ->post("/communities/{$community->slug}/join");

        $response->assertRedirect()->assertSessionHasErrors('community');
        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_join_paid_community_with_past_window_returns_error(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid(999)->create([
            'trial_mode' => Community::TRIAL_WINDOW,
            'free_until' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)
            ->from("/communities/{$community->slug}")
            ->post("/communities/{$community->slug}/join");

        $response->assertRedirect()->assertSessionHasErrors('community');
        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
    }
}
