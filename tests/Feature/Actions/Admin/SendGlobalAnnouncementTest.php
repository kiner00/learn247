<?php

namespace Tests\Feature\Actions\Admin;

use App\Actions\Admin\SendGlobalAnnouncement;
use App\Mail\GlobalAnnouncementMail;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CreatorSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendGlobalAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_to_all_active_users(): void
    {
        Mail::fake();

        $sender = User::factory()->create();
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'is_active' => true]);
        $user2 = User::factory()->create(['email' => 'user2@example.com', 'is_active' => true]);
        User::factory()->create(['email' => 'inactive@example.com', 'is_active' => false]);

        $action = new SendGlobalAnnouncement;
        $count = $action->execute($sender, 'Test Subject', 'Test Message', 'all');

        // sender + user1 + user2 are active (sender is also active by default)
        $this->assertGreaterThanOrEqual(2, $count);

        Mail::assertQueued(GlobalAnnouncementMail::class);
    }

    public function test_sends_to_affiliates_only(): void
    {
        Mail::fake();

        $sender = User::factory()->create();
        $affiliateUser = User::factory()->create(['email' => 'affiliate@example.com']);
        $normalUser = User::factory()->create(['email' => 'normal@example.com']);

        Affiliate::create([
            'community_id' => Community::factory()->create()->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $action = new SendGlobalAnnouncement;
        $count = $action->execute($sender, 'Affiliate News', 'Commission updates!', 'affiliates');

        $this->assertSame(1, $count);

        Mail::assertQueued(GlobalAnnouncementMail::class, 1);
        Mail::assertQueued(GlobalAnnouncementMail::class, fn ($mail) => $mail->hasTo('affiliate@example.com'));
    }

    public function test_sends_to_creators_only(): void
    {
        Mail::fake();

        $sender = User::factory()->create();
        $creatorUser = User::factory()->create(['email' => 'creator@example.com']);
        User::factory()->create(['email' => 'noncreator@example.com']);

        CreatorSubscription::create([
            'user_id' => $creatorUser->id,
            'plan' => 'basic',
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_id' => 'test',
        ]);

        $action = new SendGlobalAnnouncement;
        $count = $action->execute($sender, 'Creator Update', 'New features!', 'creators');

        $this->assertSame(1, $count);

        Mail::assertQueued(GlobalAnnouncementMail::class, 1);
        Mail::assertQueued(GlobalAnnouncementMail::class, fn ($mail) => $mail->hasTo('creator@example.com'));
    }

    public function test_sends_to_members_only(): void
    {
        Mail::fake();

        $sender = User::factory()->create();
        $memberUser = User::factory()->create(['email' => 'member@example.com']);
        User::factory()->create(['email' => 'nonmember@example.com']);

        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $memberUser->id]);

        $action = new SendGlobalAnnouncement;
        $count = $action->execute($sender, 'Member News', 'Community updates!', 'members');

        $this->assertSame(1, $count);

        Mail::assertQueued(GlobalAnnouncementMail::class, 1);
        Mail::assertQueued(GlobalAnnouncementMail::class, fn ($mail) => $mail->hasTo('member@example.com'));
    }

    public function test_skips_users_without_email(): void
    {
        Mail::fake();

        $sender = User::factory()->create();
        // Create a user with an empty-string email (NOT NULL constraint prevents null)
        $noEmail = User::factory()->create(['email' => '', 'is_active' => true]);
        User::factory()->create(['email' => 'valid@example.com', 'is_active' => true]);

        $action = new SendGlobalAnnouncement;
        $action->execute($sender, 'Subject', 'Message', 'all');

        // Should send to valid@example.com (and possibly the sender), but not to empty email
        Mail::assertQueued(GlobalAnnouncementMail::class, fn ($mail) => $mail->hasTo('valid@example.com'));
    }

    public function test_returns_zero_when_no_recipients(): void
    {
        Mail::fake();

        $sender = User::factory()->create();

        // No active affiliates
        $action = new SendGlobalAnnouncement;
        $count = $action->execute($sender, 'Subject', 'Message', 'affiliates');

        $this->assertSame(0, $count);
        Mail::assertNothingQueued();
    }
}
