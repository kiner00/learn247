<?php

namespace Tests\Feature\Console;

use App\Jobs\SendSequenceStepEmail;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailCampaign;
use App\Models\EmailSequence;
use App\Models\EmailSequenceEnrollment;
use App\Models\EmailSequenceStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessEmailSequencesTest extends TestCase
{
    use RefreshDatabase;

    private function createEnrollment(string $sequenceStatus = 'active', ?string $nextSendAt = null): EmailSequenceEnrollment
    {
        $community = Community::factory()->create();
        $campaign = EmailCampaign::create([
            'community_id' => $community->id,
            'name' => 'Seq Campaign',
            'type' => EmailCampaign::TYPE_SEQUENCE,
            'status' => 'draft',
        ]);
        $sequence = EmailSequence::create([
            'campaign_id' => $campaign->id,
            'community_id' => $community->id,
            'trigger_event' => EmailSequence::TRIGGER_MEMBER_JOINED,
            'status' => $sequenceStatus,
        ]);
        $step = EmailSequenceStep::create([
            'sequence_id' => $sequence->id,
            'position' => 1,
            'delay_hours' => 0,
            'subject' => 'Welcome',
            'html_body' => '<p>Hi</p>',
        ]);

        $user = User::factory()->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        return EmailSequenceEnrollment::create([
            'sequence_id' => $sequence->id,
            'community_member_id' => $member->id,
            'current_step_id' => $step->id,
            'status' => EmailSequenceEnrollment::STATUS_ACTIVE,
            'next_send_at' => $nextSendAt ?? now()->subMinute(),
            'enrolled_at' => now(),
        ]);
    }

    public function test_dispatches_jobs_for_due_enrollments(): void
    {
        Queue::fake([SendSequenceStepEmail::class]);

        $this->createEnrollment('active', now()->subMinute()->toDateTimeString());

        $this->artisan('email-sequences:process')
            ->assertSuccessful()
            ->expectsOutputToContain('Dispatched 1');

        Queue::assertPushed(SendSequenceStepEmail::class, 1);
    }

    public function test_skips_enrollments_with_inactive_sequence(): void
    {
        Queue::fake([SendSequenceStepEmail::class]);

        $this->createEnrollment('paused', now()->subMinute()->toDateTimeString());

        $this->artisan('email-sequences:process')
            ->assertSuccessful()
            ->expectsOutputToContain('Dispatched 0');

        Queue::assertNotPushed(SendSequenceStepEmail::class);
    }

    public function test_reports_no_pending_steps_when_none_due(): void
    {
        Queue::fake([SendSequenceStepEmail::class]);

        // Enrollment in the future - should not be picked up
        $this->createEnrollment('active', now()->addHour()->toDateTimeString());

        $this->artisan('email-sequences:process')
            ->assertSuccessful()
            ->expectsOutputToContain('No pending sequence steps');

        Queue::assertNotPushed(SendSequenceStepEmail::class);
    }
}
