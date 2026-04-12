<?php

namespace Tests\Feature\Jobs;

use App\Contracts\EmailProvider;
use App\Jobs\SendSequenceStepEmail;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailCampaign;
use App\Models\EmailSend;
use App\Models\EmailSequence;
use App\Models\EmailSequenceEnrollment;
use App\Models\EmailSequenceStep;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use App\Services\Email\EmailProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SendSequenceStepEmailTest extends TestCase
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

    private function createEnrollmentWithStep(array $overrides = []): array
    {
        $community = Community::factory()->create([
            'email_provider'     => 'resend',
            'resend_api_key'     => 'test-key',
            'resend_from_email'  => 'noreply@test.com',
            'resend_from_name'   => 'Test Community',
            'resend_reply_to'    => 'reply@test.com',
        ]);

        $user   = User::factory()->create(['email' => 'member@example.com', 'name' => 'John']);
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $campaign = EmailCampaign::create([
            'community_id' => $community->id,
            'name'         => 'Test Sequence Campaign',
            'type'         => EmailCampaign::TYPE_SEQUENCE,
            'status'       => 'draft',
        ]);

        $sequence = EmailSequence::create([
            'campaign_id'    => $campaign->id,
            'community_id'   => $community->id,
            'trigger_event'  => EmailSequence::TRIGGER_MEMBER_JOINED,
            'status'         => EmailSequence::STATUS_ACTIVE,
        ]);

        $step = EmailSequenceStep::create(array_merge([
            'sequence_id' => $sequence->id,
            'position'    => 1,
            'delay_hours' => 0,
            'subject'     => 'Welcome!',
            'html_body'   => '<p>Hello {{user_name}}, welcome to {{community_name}}!</p>',
        ], $overrides['step'] ?? []));

        $enrollment = EmailSequenceEnrollment::create([
            'sequence_id'         => $sequence->id,
            'community_member_id' => $member->id,
            'current_step_id'     => $step->id,
            'steps_completed'     => 0,
            'status'              => EmailSequenceEnrollment::STATUS_ACTIVE,
            'next_send_at'        => now(),
            'enrolled_at'         => now(),
        ]);

        return compact('community', 'user', 'member', 'campaign', 'sequence', 'step', 'enrollment');
    }

    // ── Happy path ──────────────────────────────────────────────────────────

    public function test_sends_email_and_creates_send_record(): void
    {
        $data = $this->createEnrollmentWithStep();

        $this->fakeProvider()
            ->shouldReceive('sendEmail')
            ->once()
            ->andReturn(['id' => 'resend_123']);

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $this->assertDatabaseHas('email_sends', [
            'community_id'        => $data['community']->id,
            'community_member_id' => $data['member']->id,
            'resend_email_id'     => 'resend_123',
            'status'              => 'sent',
        ]);
    }

    public function test_interpolates_template_variables(): void
    {
        $data = $this->createEnrollmentWithStep([
            'step' => ['html_body' => '<p>Hi {{user_name}}, email: {{user_email}}, community: {{community_name}}</p>'],
        ]);

        $capturedHtml = null;
        $this->fakeProvider()
            ->shouldReceive('sendEmail')
            ->once()
            ->withArgs(function ($community, $params) use (&$capturedHtml) {
                $capturedHtml = $params['html'];
                return true;
            })
            ->andReturn(['id' => 'email_456']);

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $this->assertStringContainsString('Hi John', $capturedHtml);
        $this->assertStringContainsString('member@example.com', $capturedHtml);
        $this->assertStringContainsString($data['community']->name, $capturedHtml);
        $this->assertStringContainsString('Unsubscribe', $capturedHtml);
    }

    public function test_advances_to_next_step_after_send(): void
    {
        $data = $this->createEnrollmentWithStep();

        $nextStep = EmailSequenceStep::create([
            'sequence_id' => $data['sequence']->id,
            'position'    => 2,
            'delay_hours' => 24,
            'subject'     => 'Follow up',
            'html_body'   => '<p>Follow up email</p>',
        ]);

        $this->fakeProvider()
            ->shouldReceive('sendEmail')->once()->andReturn(['id' => 'email_789']);

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $data['enrollment']->refresh();
        $this->assertEquals($nextStep->id, $data['enrollment']->current_step_id);
        $this->assertEquals(1, $data['enrollment']->steps_completed);
        $this->assertNotNull($data['enrollment']->next_send_at);
        $this->assertEquals(EmailSequenceEnrollment::STATUS_ACTIVE, $data['enrollment']->status);
    }

    public function test_completes_sequence_when_no_more_steps(): void
    {
        $data = $this->createEnrollmentWithStep();

        $this->fakeProvider()
            ->shouldReceive('sendEmail')->once()->andReturn(['id' => 'email_done']);

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $data['enrollment']->refresh();
        $this->assertNull($data['enrollment']->current_step_id);
        $this->assertEquals(1, $data['enrollment']->steps_completed);
        $this->assertEquals(EmailSequenceEnrollment::STATUS_COMPLETED, $data['enrollment']->status);
        $this->assertNotNull($data['enrollment']->completed_at);
        $this->assertNull($data['enrollment']->next_send_at);
    }

    // ── Early returns ───────────────────────────────────────────────────────

    public function test_skips_when_enrollment_not_active(): void
    {
        $data = $this->createEnrollmentWithStep();
        $data['enrollment']->update(['status' => EmailSequenceEnrollment::STATUS_CANCELLED]);

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $this->assertDatabaseCount('email_sends', 0);
    }

    public function test_skips_when_step_is_null(): void
    {
        $data = $this->createEnrollmentWithStep();
        $data['enrollment']->update(['current_step_id' => null]);

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $this->assertDatabaseCount('email_sends', 0);
    }

    public function test_cancels_enrollment_when_user_is_unsubscribed(): void
    {
        $data = $this->createEnrollmentWithStep();

        EmailUnsubscribe::create([
            'community_id'    => $data['community']->id,
            'user_id'         => $data['user']->id,
            'unsubscribed_at' => now(),
        ]);

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $data['enrollment']->refresh();
        $this->assertEquals(EmailSequenceEnrollment::STATUS_CANCELLED, $data['enrollment']->status);
        $this->assertDatabaseCount('email_sends', 0);
    }

    public function test_skips_when_user_has_no_email(): void
    {
        $data = $this->createEnrollmentWithStep();
        // Delete user so user relation returns null (email column is NOT NULL)
        $data['user']->delete();

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $this->assertDatabaseCount('email_sends', 0);
    }

    // ── Error handling ──────────────────────────────────────────────────────

    public function test_logs_error_and_returns_when_unknown_provider(): void
    {
        $data = $this->createEnrollmentWithStep();
        $data['community']->update(['email_provider' => 'nonexistent_provider']);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'SendSequenceStepEmail'));

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $this->assertDatabaseCount('email_sends', 0);
    }

    public function test_records_failed_send_when_email_dispatch_fails(): void
    {
        $data = $this->createEnrollmentWithStep();

        $this->fakeProvider()
            ->shouldReceive('sendEmail')
            ->once()
            ->andThrow(new \Exception('SMTP timeout'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'send failed'));

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $this->assertDatabaseHas('email_sends', [
            'community_id'        => $data['community']->id,
            'community_member_id' => $data['member']->id,
            'status'              => 'failed',
        ]);

        $data['enrollment']->refresh();
        $this->assertEquals(0, $data['enrollment']->steps_completed);
    }

    // ── From address fallback ───────────────────────────────────────────────

    public function test_uses_step_from_email_when_provided(): void
    {
        $data = $this->createEnrollmentWithStep([
            'step' => [
                'from_email' => 'custom@sender.com',
                'from_name'  => 'Custom Sender',
            ],
        ]);

        $capturedFrom = null;
        $this->fakeProvider()
            ->shouldReceive('sendEmail')
            ->once()
            ->withArgs(function ($community, $params) use (&$capturedFrom) {
                $capturedFrom = $params['from'];
                return true;
            })
            ->andReturn(['id' => 'email_from']);

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $this->assertEquals('Custom Sender <custom@sender.com>', $capturedFrom);
    }

    public function test_falls_back_to_community_from_email(): void
    {
        $data = $this->createEnrollmentWithStep([
            'step' => [
                'from_email' => null,
                'from_name'  => null,
            ],
        ]);

        $capturedFrom = null;
        $this->fakeProvider()
            ->shouldReceive('sendEmail')
            ->once()
            ->withArgs(function ($community, $params) use (&$capturedFrom) {
                $capturedFrom = $params['from'];
                return true;
            })
            ->andReturn(['id' => 'email_fb']);

        $job = new SendSequenceStepEmail($data['enrollment']);
        $job->handle();

        $this->assertEquals('Test Community <noreply@test.com>', $capturedFrom);
    }
}
