<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendEmailBroadcast;
use App\Jobs\SendEmailBroadcastBatch;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailBroadcast;
use App\Models\EmailCampaign;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendEmailBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function createBroadcastWithMembers(int $memberCount = 3, array $broadcastOverrides = []): array
    {
        $community = Community::factory()->create();
        $campaign = EmailCampaign::create([
            'community_id' => $community->id,
            'name'         => 'Test Campaign',
            'type'         => EmailCampaign::TYPE_BROADCAST,
            'status'       => 'draft',
        ]);
        $broadcast = EmailBroadcast::create(array_merge([
            'campaign_id'  => $campaign->id,
            'community_id' => $community->id,
            'subject'      => 'Test Subject',
            'html_body'    => '<p>Hello</p>',
            'status'       => EmailBroadcast::STATUS_DRAFT,
        ], $broadcastOverrides));

        $members = [];
        for ($i = 0; $i < $memberCount; $i++) {
            $user = User::factory()->create();
            $members[] = CommunityMember::factory()->create([
                'community_id' => $community->id,
                'user_id'      => $user->id,
                'is_blocked'   => false,
            ]);
        }

        return [$community, $broadcast, $campaign, $members];
    }

    public function test_dispatches_batch_jobs_for_recipients(): void
    {
        Queue::fake([SendEmailBroadcastBatch::class]);

        [$community, $broadcast, $campaign, $members] = $this->createBroadcastWithMembers(3);

        $job = new SendEmailBroadcast($broadcast);
        $job->handle();

        $broadcast->refresh();
        $this->assertEquals(EmailBroadcast::STATUS_SENDING, $broadcast->status);
        $this->assertEquals(3, $broadcast->total_recipients);

        Queue::assertPushed(SendEmailBroadcastBatch::class);
    }

    public function test_marks_sent_immediately_when_no_recipients(): void
    {
        Queue::fake([SendEmailBroadcastBatch::class]);

        $community = Community::factory()->create();
        $campaign = EmailCampaign::create([
            'community_id' => $community->id,
            'name'         => 'Empty Campaign',
            'type'         => EmailCampaign::TYPE_BROADCAST,
            'status'       => 'draft',
        ]);
        $broadcast = EmailBroadcast::create([
            'campaign_id'  => $campaign->id,
            'community_id' => $community->id,
            'subject'      => 'No recipients',
            'html_body'    => '<p>Nobody</p>',
            'status'       => EmailBroadcast::STATUS_DRAFT,
        ]);

        $job = new SendEmailBroadcast($broadcast);
        $job->handle();

        $broadcast->refresh();
        $this->assertEquals(EmailBroadcast::STATUS_SENT, $broadcast->status);
        $this->assertNotNull($broadcast->sent_at);

        Queue::assertNotPushed(SendEmailBroadcastBatch::class);
    }

    public function test_excludes_unsubscribed_users(): void
    {
        Queue::fake([SendEmailBroadcastBatch::class]);

        [$community, $broadcast, $campaign, $members] = $this->createBroadcastWithMembers(3);

        // Unsubscribe the first member's user
        EmailUnsubscribe::create([
            'community_id'    => $community->id,
            'user_id'         => $members[0]->user_id,
            'unsubscribed_at' => now(),
        ]);

        $job = new SendEmailBroadcast($broadcast);
        $job->handle();

        $broadcast->refresh();
        $this->assertEquals(2, $broadcast->total_recipients);
    }

    public function test_excludes_blocked_members(): void
    {
        Queue::fake([SendEmailBroadcastBatch::class]);

        [$community, $broadcast, $campaign, $members] = $this->createBroadcastWithMembers(2);

        // Block one member
        $members[0]->update(['is_blocked' => true]);

        $job = new SendEmailBroadcast($broadcast);
        $job->handle();

        $broadcast->refresh();
        $this->assertEquals(1, $broadcast->total_recipients);
    }
}
