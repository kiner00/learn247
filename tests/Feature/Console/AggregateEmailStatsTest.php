<?php

namespace Tests\Feature\Console;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailDailyStat;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AggregateEmailStatsTest extends TestCase
{
    use RefreshDatabase;

    private function insertEmailSend(int $communityId, string $createdAt, array $overrides = []): void
    {
        $user = User::factory()->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $communityId,
            'user_id' => $user->id,
        ]);

        DB::table('email_sends')->insert(array_merge([
            'community_id' => $communityId,
            'community_member_id' => $member->id,
            'status' => 'delivered',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ], $overrides));
    }

    public function test_aggregates_email_stats_for_yesterday_by_default(): void
    {
        $community = Community::factory()->create();
        $yesterday = now()->subDay()->toDateString();

        $this->insertEmailSend($community->id, $yesterday, [
            'status' => 'delivered',
            'opened_at' => $yesterday,
        ]);
        $this->insertEmailSend($community->id, $yesterday, [
            'status' => 'bounced',
        ]);

        $this->artisan('email-stats:aggregate')
            ->assertSuccessful()
            ->expectsOutputToContain('Aggregated stats for 1 communities');

        $stat = EmailDailyStat::where('community_id', $community->id)->first();
        $this->assertNotNull($stat);
        $this->assertEquals($yesterday, $stat->date->toDateString());
        $this->assertDatabaseHas('email_daily_stats', [
            'community_id' => $community->id,
            'sent' => 2,
            'delivered' => 1,
            'opened' => 1,
            'bounced' => 1,
        ]);
    }

    public function test_aggregates_for_specific_date_option(): void
    {
        $community = Community::factory()->create();
        $date = '2025-06-15';

        $this->insertEmailSend($community->id, $date, [
            'status' => 'delivered',
            'opened_at' => $date,
            'clicked_at' => $date,
        ]);

        $this->artisan('email-stats:aggregate --date=2025-06-15')
            ->assertSuccessful()
            ->expectsOutputToContain('Aggregated stats for 1 communities');

        $stat = EmailDailyStat::where('community_id', $community->id)->first();
        $this->assertNotNull($stat);
        $this->assertEquals($date, $stat->date->toDateString());
        $this->assertEquals(1, $stat->sent);
        $this->assertEquals(1, $stat->delivered);
        $this->assertEquals(1, $stat->opened);
        $this->assertEquals(1, $stat->clicked);
    }

    public function test_includes_unsubscribe_counts(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $yesterday = now()->subDay()->toDateString();

        $this->insertEmailSend($community->id, $yesterday);

        EmailUnsubscribe::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'unsubscribed_at' => $yesterday,
        ]);

        $this->artisan('email-stats:aggregate')
            ->assertSuccessful();

        $stat = EmailDailyStat::where('community_id', $community->id)->first();
        $this->assertNotNull($stat);
        $this->assertEquals($yesterday, $stat->date->toDateString());
        $this->assertDatabaseHas('email_daily_stats', [
            'community_id' => $community->id,
            'unsubscribed' => 1,
        ]);
    }

    public function test_reports_zero_communities_when_no_sends(): void
    {
        $this->artisan('email-stats:aggregate')
            ->assertSuccessful()
            ->expectsOutputToContain('Aggregated stats for 0 communities');
    }
}
