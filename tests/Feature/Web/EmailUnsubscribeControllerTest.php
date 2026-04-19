<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailUnsubscribeControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Happy Path ─────────────────────────────────────────────────────────────

    public function test_valid_signed_url_unsubscribes_member(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $user = User::factory()->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $url = URL::signedRoute('email.unsubscribe', [
            'community' => $community->slug,
            'member' => $member->id,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Email/Unsubscribed')
                ->where('communityName', $community->name)
            );

        $this->assertDatabaseHas('email_unsubscribes', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'reason' => 'manual',
        ]);
    }

    public function test_unsubscribe_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $user = User::factory()->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        EmailUnsubscribe::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'reason' => 'manual',
            'unsubscribed_at' => now(),
        ]);

        $url = URL::signedRoute('email.unsubscribe', [
            'community' => $community->slug,
            'member' => $member->id,
        ]);

        $this->get($url)->assertOk();

        // Should still be exactly one record (firstOrCreate)
        $this->assertDatabaseCount('email_unsubscribes', 1);
    }

    // ─── Unsigned URL ───────────────────────────────────────────────────────────

    public function test_unsigned_url_returns_403(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $user = User::factory()->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $this->get(route('email.unsubscribe', [
            'community' => $community->slug,
            'member' => $member->id,
        ]))->assertForbidden();
    }

    // ─── Member from wrong community ───────────────────────────────────────────

    public function test_member_from_different_community_returns_404(): void
    {
        $community1 = Community::factory()->create();
        $community2 = Community::factory()->create();
        $member = CommunityMember::factory()->create(['community_id' => $community2->id]);

        $url = URL::signedRoute('email.unsubscribe', [
            'community' => $community1->slug,
            'member' => $member->id,
        ]);

        $this->get($url)->assertNotFound();
    }
}
