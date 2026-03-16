<?php

namespace Tests\Feature\Web;

use App\Actions\Community\AcceptInvite;
use App\Actions\Community\SendInvite;
use App\Jobs\SendBatchInvites;
use App\Mail\CommunityInviteMail;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunityInviteControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── accept ────────────────────────────────────────────────────────────────

    public function test_accept_redirects_guest_to_login(): void
    {
        $community = Community::factory()->create();
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => 'guest@example.com',
            'token'        => Str::random(64),
            'expires_at'   => now()->addDays(7),
        ]);

        $this->get(route('community.invite.accept', $invite->token))
            ->assertRedirect(route('login', ['redirect' => "/invite/{$invite->token}"]));
    }

    public function test_accept_with_valid_token_joins_authenticated_user(): void
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        $community = Community::factory()->create();
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => 'member@example.com',
            'token'        => Str::random(64),
            'expires_at'   => now()->addDays(7),
        ]);

        $this->actingAs($user)
            ->get(route('community.invite.accept', $invite->token))
            ->assertRedirect(route('communities.show', $community->slug));

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
    }

    public function test_accept_with_expired_token_shows_error(): void
    {
        $user = User::factory()->create(['email' => 'expired@example.com']);
        $community = Community::factory()->create();
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => 'expired@example.com',
            'token'        => Str::random(64),
            'expires_at'   => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get(route('community.invite.accept', $invite->token))
            ->assertRedirect(route('communities.about', $community->slug))
            ->assertSessionHas('error', 'This invite link has expired.');
    }

    public function test_accept_with_invalid_token_returns_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('community.invite.accept', 'invalid-token-xyz'))
            ->assertNotFound();
    }

    public function test_accept_with_wrong_email_shows_error(): void
    {
        $user = User::factory()->create(['email' => 'wrong@example.com']);
        $community = Community::factory()->create();
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => 'correct@example.com',
            'token'        => Str::random(64),
            'expires_at'   => now()->addDays(7),
        ]);

        $this->actingAs($user)
            ->get(route('community.invite.accept', $invite->token))
            ->assertRedirect(route('communities.about', $community->slug))
            ->assertSessionHas('error');
    }

    public function test_accept_already_accepted_invite_redirects_to_show(): void
    {
        $user = User::factory()->create(['email' => 'already@example.com']);
        $community = Community::factory()->create();
        $invite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => 'already@example.com',
            'token'        => Str::random(64),
            'accepted_at'  => now(),
            'expires_at'   => now()->addDays(7),
        ]);

        $this->actingAs($user)
            ->get(route('community.invite.accept', $invite->token))
            ->assertRedirect(route('communities.show', $community->slug));
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_store_single_email_by_owner_sends_invite(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('communities.invite', $community), ['email' => 'new@example.com'])
            ->assertRedirect();

        Mail::assertQueued(CommunityInviteMail::class, function ($mail) {
            return $mail->hasTo('new@example.com');
        });

        $this->assertDatabaseHas('community_invites', [
            'community_id' => $community->id,
            'email'        => 'new@example.com',
        ]);
    }

    public function test_store_csv_by_owner_dispatches_batch_job(): void
    {
        Bus::fake([SendBatchInvites::class]);

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $csv = UploadedFile::fake()->createWithContent('emails.csv', "one@test.com\ntwo@test.com\n");

        $this->actingAs($owner)
            ->post(route('communities.invite', $community), ['csv' => $csv])
            ->assertRedirect();

        Bus::assertDispatched(SendBatchInvites::class);
    }

    public function test_store_by_non_owner_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $nonOwner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($nonOwner)
            ->post(route('communities.invite', $community), ['email' => 'test@example.com'])
            ->assertForbidden();
    }

    public function test_store_requires_auth(): void
    {
        $community = Community::factory()->create();

        $this->post(route('communities.invite', $community), ['email' => 'test@example.com'])
            ->assertRedirect('/login');
    }

    public function test_store_single_email_returns_json_on_ajax(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('communities.invite', $community), ['email' => 'ajax@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message']);
    }

    public function test_store_validates_email_field(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('communities.invite', $community), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
    }
}
