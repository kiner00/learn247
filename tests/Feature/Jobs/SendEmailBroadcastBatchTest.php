<?php

namespace Tests\Feature\Jobs;

use App\Contracts\EmailProvider;
use App\Jobs\SendEmailBroadcastBatch;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailBroadcast;
use App\Models\EmailCampaign;
use App\Models\EmailSend;
use App\Models\User;
use App\Services\Email\EmailProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SendEmailBroadcastBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        EmailProviderFactory::$fakeProvider = null;
        parent::tearDown();
    }

    private function fakeProvider(): \Mockery\MockInterface
    {
        $mock = \Mockery::mock(EmailProvider::class);
        EmailProviderFactory::$fakeProvider = $mock;

        return $mock;
    }

    private function createBroadcastWithMembers(int $memberCount = 3, array $broadcastOverrides = []): array
    {
        $community = Community::factory()->create([
            'email_provider'    => 'resend',
            'resend_api_key'    => 'test-key',
            'resend_from_email' => 'noreply@test.com',
            'resend_from_name'  => 'Test Community',
            'resend_reply_to'   => 'reply@test.com',
        ]);

        $campaign = EmailCampaign::create([
            'community_id' => $community->id,
            'name'         => 'Batch Campaign',
            'type'         => EmailCampaign::TYPE_BROADCAST,
            'status'       => 'sending',
        ]);

        $broadcast = EmailBroadcast::create(array_merge([
            'campaign_id'      => $campaign->id,
            'community_id'     => $community->id,
            'subject'          => 'Batch Subject',
            'html_body'        => '<p>Hello {{user_name}}</p>',
            'status'           => EmailBroadcast::STATUS_SENDING,
            'total_recipients' => $memberCount,
            'total_sent'       => 0,
            'total_failed'     => 0,
        ], $broadcastOverrides));

        $members = [];
        for ($i = 0; $i < $memberCount; $i++) {
            $user      = User::factory()->create();
            $members[] = CommunityMember::factory()->create([
                'community_id' => $community->id,
                'user_id'      => $user->id,
            ]);
        }

        return compact('community', 'campaign', 'broadcast', 'members');
    }

    // ── Happy path ──────────────────────────────────────────────────────────

    public function test_sends_batch_and_creates_send_records(): void
    {
        $data      = $this->createBroadcastWithMembers(2);
        $memberIds = collect($data['members'])->pluck('id')->all();

        $this->fakeProvider()
            ->shouldReceive('sendBatch')
            ->once()
            ->andReturn([
                ['id' => 'email_1'],
                ['id' => 'email_2'],
            ]);

        $job = new SendEmailBroadcastBatch($data['broadcast'], $memberIds);
        $job->handle();

        $this->assertEquals(2, EmailSend::where('status', 'sent')->count());
        $this->assertDatabaseHas('email_sends', [
            'broadcast_id'    => $data['broadcast']->id,
            'resend_email_id' => 'email_1',
            'status'          => 'sent',
        ]);

        $data['broadcast']->refresh();
        $this->assertEquals(2, $data['broadcast']->total_sent);
    }

    public function test_marks_broadcast_as_sent_when_all_complete(): void
    {
        $data      = $this->createBroadcastWithMembers(2);
        $memberIds = collect($data['members'])->pluck('id')->all();

        $this->fakeProvider()
            ->shouldReceive('sendBatch')
            ->once()
            ->andReturn([['id' => 'e1'], ['id' => 'e2']]);

        $job = new SendEmailBroadcastBatch($data['broadcast'], $memberIds);
        $job->handle();

        $data['broadcast']->refresh();
        $this->assertEquals(EmailBroadcast::STATUS_SENT, $data['broadcast']->status);
        $this->assertNotNull($data['broadcast']->sent_at);

        $data['campaign']->refresh();
        $this->assertEquals('sent', $data['campaign']->status);
    }

    public function test_interpolates_template_variables_in_html(): void
    {
        $data = $this->createBroadcastWithMembers(1, [
            'html_body' => '<p>Hi {{user_name}}, email: {{user_email}}, community: {{community_name}}</p>',
        ]);
        $memberIds = collect($data['members'])->pluck('id')->all();

        $capturedBatch = null;
        $this->fakeProvider()
            ->shouldReceive('sendBatch')
            ->once()
            ->withArgs(function ($community, $emails) use (&$capturedBatch) {
                $capturedBatch = $emails;
                return true;
            })
            ->andReturn([['id' => 'e1']]);

        $job = new SendEmailBroadcastBatch($data['broadcast'], $memberIds);
        $job->handle();

        $html = $capturedBatch[0]['html'];
        $user = $data['members'][0]->user;
        $this->assertStringContainsString($user->name, $html);
        $this->assertStringContainsString($user->email, $html);
        $this->assertStringContainsString($data['community']->name, $html);
        $this->assertStringContainsString('Unsubscribe', $html);
    }

    // ── Edge cases ──────────────────────────────────────────────────────────

    public function test_skips_members_without_user(): void
    {
        $data = $this->createBroadcastWithMembers(2, ['total_recipients' => 1]);
        // Delete the user record so user relation returns null
        $data['members'][0]->user->delete();
        $memberIds = collect($data['members'])->pluck('id')->all();

        $this->fakeProvider()
            ->shouldReceive('sendBatch')
            ->once()
            ->andReturn([['id' => 'e1']]);

        $job = new SendEmailBroadcastBatch($data['broadcast'], $memberIds);
        $job->handle();

        // Only 1 email send record for the member with a valid user
        $this->assertEquals(1, EmailSend::count());
    }

    public function test_returns_early_when_no_valid_members(): void
    {
        $data = $this->createBroadcastWithMembers(1);
        // Delete user so the member has no associated user
        $data['members'][0]->user->delete();
        $memberIds = collect($data['members'])->pluck('id')->all();

        $this->fakeProvider()
            ->shouldNotReceive('sendBatch');

        $job = new SendEmailBroadcastBatch($data['broadcast'], $memberIds);
        $job->handle();

        $this->assertEquals(0, EmailSend::count());
    }

    // ── Error handling ──────────────────────────────────────────────────────

    public function test_logs_and_returns_when_unknown_provider(): void
    {
        $data = $this->createBroadcastWithMembers(1);
        $data['community']->update(['email_provider' => 'nonexistent_provider']);
        $memberIds = collect($data['members'])->pluck('id')->all();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'SendEmailBroadcastBatch'));

        $job = new SendEmailBroadcastBatch($data['broadcast'], $memberIds);
        $job->handle();

        $this->assertEquals(0, EmailSend::count());
    }

    public function test_marks_sends_as_failed_when_batch_throws(): void
    {
        $data      = $this->createBroadcastWithMembers(2);
        $memberIds = collect($data['members'])->pluck('id')->all();

        $this->fakeProvider()
            ->shouldReceive('sendBatch')
            ->once()
            ->andThrow(new \Exception('API error'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'batch send failed'));

        $job = new SendEmailBroadcastBatch($data['broadcast'], $memberIds);
        $job->handle();

        $this->assertEquals(2, EmailSend::where('status', 'failed')->count());
        $this->assertDatabaseHas('email_sends', [
            'status'        => 'failed',
            'failed_reason' => 'API error',
        ]);

        $data['broadcast']->refresh();
        $this->assertEquals(2, $data['broadcast']->total_failed);
    }

    // ── From address fallbacks ──────────────────────────────────────────────

    public function test_uses_broadcast_from_when_set(): void
    {
        $data = $this->createBroadcastWithMembers(1, [
            'from_email' => 'broadcast@custom.com',
            'from_name'  => 'Broadcast Sender',
            'reply_to'   => 'broadcast-reply@custom.com',
        ]);
        $memberIds = collect($data['members'])->pluck('id')->all();

        $capturedBatch = null;
        $this->fakeProvider()
            ->shouldReceive('sendBatch')
            ->once()
            ->withArgs(function ($community, $emails) use (&$capturedBatch) {
                $capturedBatch = $emails;
                return true;
            })
            ->andReturn([['id' => 'e1']]);

        $job = new SendEmailBroadcastBatch($data['broadcast'], $memberIds);
        $job->handle();

        $this->assertEquals('Broadcast Sender <broadcast@custom.com>', $capturedBatch[0]['from']);
        $this->assertEquals(['broadcast-reply@custom.com'], $capturedBatch[0]['reply_to']);
    }

    public function test_falls_back_to_community_from_when_broadcast_has_none(): void
    {
        $data = $this->createBroadcastWithMembers(1, [
            'from_email' => null,
            'from_name'  => null,
            'reply_to'   => null,
        ]);
        $memberIds = collect($data['members'])->pluck('id')->all();

        $capturedBatch = null;
        $this->fakeProvider()
            ->shouldReceive('sendBatch')
            ->once()
            ->withArgs(function ($community, $emails) use (&$capturedBatch) {
                $capturedBatch = $emails;
                return true;
            })
            ->andReturn([['id' => 'e1']]);

        $job = new SendEmailBroadcastBatch($data['broadcast'], $memberIds);
        $job->handle();

        $this->assertEquals('Test Community <noreply@test.com>', $capturedBatch[0]['from']);
        $this->assertEquals(['reply@test.com'], $capturedBatch[0]['reply_to']);
    }
}
