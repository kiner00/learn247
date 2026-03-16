<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendBatchInvites;
use App\Mail\CommunityInviteMail;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendBatchInvitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_invite_emails_and_creates_invite_records(): void
    {
        Mail::fake();

        $community = Community::factory()->create();
        $emails = ['alice@example.com', 'bob@example.com'];

        $job = new SendBatchInvites($community, $emails);
        $job->handle();

        $this->assertDatabaseHas('community_invites', [
            'community_id' => $community->id,
            'email'        => 'alice@example.com',
        ]);
        $this->assertDatabaseHas('community_invites', [
            'community_id' => $community->id,
            'email'        => 'bob@example.com',
        ]);

        Mail::assertSent(CommunityInviteMail::class, 2);
    }

    public function test_invite_record_has_token_and_expiry(): void
    {
        Mail::fake();

        $community = Community::factory()->create();

        $job = new SendBatchInvites($community, ['test@example.com']);
        $job->handle();

        $invite = CommunityInvite::where('email', 'test@example.com')->first();
        $this->assertNotNull($invite->token);
        $this->assertNotNull($invite->expires_at);
        $this->assertTrue($invite->expires_at->isFuture());
    }

    public function test_skips_emails_of_existing_members(): void
    {
        Mail::fake();

        $community = Community::factory()->create();
        $existingUser = User::factory()->create(['email' => 'member@example.com']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $existingUser->id,
        ]);

        $emails = ['member@example.com', 'newperson@example.com'];

        $job = new SendBatchInvites($community, $emails);
        $job->handle();

        $this->assertDatabaseMissing('community_invites', [
            'community_id' => $community->id,
            'email'        => 'member@example.com',
        ]);
        $this->assertDatabaseHas('community_invites', [
            'community_id' => $community->id,
            'email'        => 'newperson@example.com',
        ]);

        Mail::assertSent(CommunityInviteMail::class, 1);
        Mail::assertSent(CommunityInviteMail::class, function (CommunityInviteMail $mail) {
            return $mail->invite->email === 'newperson@example.com';
        });
    }

    public function test_updates_existing_invite_with_new_token(): void
    {
        Mail::fake();

        $community = Community::factory()->create();

        $oldInvite = CommunityInvite::create([
            'community_id' => $community->id,
            'email'        => 'repeat@example.com',
            'token'        => 'old-token-value',
            'expires_at'   => now()->subDay(),
        ]);

        $job = new SendBatchInvites($community, ['repeat@example.com']);
        $job->handle();

        $updatedInvite = CommunityInvite::where('email', 'repeat@example.com')->first();
        $this->assertNotEquals('old-token-value', $updatedInvite->token);
        $this->assertTrue($updatedInvite->expires_at->isFuture());

        Mail::assertSent(CommunityInviteMail::class, 1);
    }

    public function test_handles_empty_email_list(): void
    {
        Mail::fake();

        $community = Community::factory()->create();

        $job = new SendBatchInvites($community, []);
        $job->handle();

        $this->assertCount(0, CommunityInvite::all());
        Mail::assertNothingSent();
    }
}
