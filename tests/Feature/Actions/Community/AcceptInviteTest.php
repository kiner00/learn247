<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\AcceptInvite;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptInviteTest extends TestCase
{
    use RefreshDatabase;

    private AcceptInvite $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AcceptInvite();
    }

    public function test_expired_invite_returns_failure(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => $user->email,
            'token'        => 'abc',
            'expires_at'   => now()->subDay(),
        ]);

        $result = $this->action->execute($user, $invite);

        $this->assertFalse($result['success']);
        $this->assertSame('This invite link has expired.', $result['message']);
        $this->assertSame('about', $result['redirect']);
    }

    public function test_already_accepted_invite_returns_success_with_show_redirect(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => $user->email,
            'token'        => 'abc',
            'accepted_at'  => now(),
            'expires_at'   => now()->addDays(7),
        ]);

        $result = $this->action->execute($user, $invite);

        $this->assertTrue($result['success']);
        $this->assertSame('You already have access to this community.', $result['message']);
        $this->assertSame('show', $result['redirect']);
    }

    public function test_wrong_email_returns_failure(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.com']);
        $community = Community::factory()->create();
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => 'bob@example.com',
            'token'        => 'abc',
            'expires_at'   => now()->addDays(7),
        ]);

        $result = $this->action->execute($user, $invite);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('bob@example.com', $result['message']);
        $this->assertSame('about', $result['redirect']);
    }

    public function test_accept_invite_for_free_community_creates_member_only(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $community = Community::factory()->create(['price' => 0]);
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => 'test@example.com',
            'token'        => 'abc',
            'expires_at'   => now()->addDays(7),
        ]);

        $result = $this->action->execute($user, $invite);

        $this->assertTrue($result['success']);
        $this->assertSame('show', $result['redirect']);
        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
        $this->assertDatabaseMissing('subscriptions', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
    }

    public function test_accept_invite_for_paid_community_creates_member_and_subscription(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $community = Community::factory()->create(['price' => 500]);
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => 'test@example.com',
            'token'        => 'abc',
            'expires_at'   => now()->addDays(7),
        ]);

        $result = $this->action->execute($user, $invite);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString($community->name, $result['message']);
        $this->assertSame('show', $result['redirect']);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'role'         => CommunityMember::ROLE_MEMBER,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => null,
        ]);

        $invite->refresh();
        $this->assertNotNull($invite->accepted_at);
    }
}
