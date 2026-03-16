<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\SendInvite;
use App\Jobs\SendBatchInvites;
use App\Mail\CommunityInviteMail;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendInviteTest extends TestCase
{
    use RefreshDatabase;

    private SendInvite $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new SendInvite();
    }

    public function test_single_invite_returns_error_when_already_a_member(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create(['email' => 'member@example.com']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $result = $this->action->single($community, 'member@example.com');

        $this->assertSame('error', $result['type']);
        $this->assertStringContainsString('already a member', $result['message']);
    }

    public function test_single_invite_sends_mail_for_new_email(): void
    {
        Mail::fake();
        $community = Community::factory()->create();

        $result = $this->action->single($community, 'new@example.com');

        $this->assertSame('success', $result['type']);
        $this->assertStringContainsString('new@example.com', $result['message']);
        Mail::assertSent(CommunityInviteMail::class, function (CommunityInviteMail $mail) {
            return $mail->hasTo('new@example.com');
        });
        $this->assertDatabaseHas('community_invites', [
            'community_id' => $community->id,
            'email'        => 'new@example.com',
        ]);
    }

    public function test_batch_returns_error_when_emails_array_is_empty(): void
    {
        $community = Community::factory()->create();

        $result = $this->action->batch($community, []);

        $this->assertSame('error', $result['type']);
        $this->assertStringContainsString('No valid email addresses', $result['message']);
    }

    public function test_batch_dispatches_job_and_returns_count(): void
    {
        Bus::fake();
        $community = Community::factory()->create();

        $result = $this->action->batch($community, ['a@x.com', 'b@x.com']);

        $this->assertSame('success', $result['type']);
        $this->assertStringContainsString('2 invites', $result['message']);
        Bus::assertDispatched(SendBatchInvites::class);
    }

    public function test_batch_deduplicates_emails(): void
    {
        Bus::fake();
        $community = Community::factory()->create();

        $result = $this->action->batch($community, ['a@x.com', 'A@X.COM', 'b@x.com']);

        $this->assertStringContainsString('2 invites', $result['message']);
    }

    public function test_batch_singular_count_message(): void
    {
        Bus::fake();
        $community = Community::factory()->create();

        $result = $this->action->batch($community, ['single@x.com']);

        $this->assertStringContainsString('1 invite ', $result['message']);
        $this->assertStringNotContainsString('1 invites', $result['message']);
    }
}
