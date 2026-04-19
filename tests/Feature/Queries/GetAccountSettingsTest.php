<?php

namespace Tests\Feature\Queries;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Account\GetAccountSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private GetAccountSettings $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = new GetAccountSettings;
    }

    public function test_returns_profile_user_data(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com',
            'bio' => 'A bio',
            'avatar' => '/avatar.jpg',
        ]);

        $result = $this->query->execute($user);

        $this->assertSame('John', $result['profileUser']['first_name']);
        $this->assertSame('Doe', $result['profileUser']['last_name']);
        $this->assertSame('johndoe', $result['profileUser']['username']);
        $this->assertSame('john@example.com', $result['profileUser']['email']);
        $this->assertSame('A bio', $result['profileUser']['bio']);
        $this->assertSame('/avatar.jpg', $result['profileUser']['avatar']);
    }

    public function test_returns_default_tab(): void
    {
        $user = User::factory()->create();
        $result = $this->query->execute($user);

        $this->assertSame('communities', $result['tab']);
    }

    public function test_returns_custom_tab(): void
    {
        $user = User::factory()->create();
        $result = $this->query->execute($user, 'notifications');

        $this->assertSame('notifications', $result['tab']);
    }

    public function test_returns_memberships_with_community_data(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['name' => 'Test Community']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $result = $this->query->execute($user);

        $this->assertCount(1, $result['memberships']);
        $this->assertSame('Test Community', $result['memberships'][0]['name']);
        $this->assertSame($community->slug, $result['memberships'][0]['slug']);
    }

    public function test_memberships_include_active_subscription_info(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $sub = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $result = $this->query->execute($user);

        $membership = $result['memberships'][0];
        $this->assertSame($sub->id, $membership['subscription_id']);
        $this->assertNotNull($membership['expires_at']);
        $this->assertFalse($membership['is_recurring']);
        $this->assertFalse($membership['is_auto_renewing']);
    }

    public function test_memberships_show_recurring_subscription(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_plan_id' => 'plan_123',
            'recurring_status' => 'ACTIVE',
        ]);

        $result = $this->query->execute($user);

        $membership = $result['memberships'][0];
        $this->assertTrue($membership['is_recurring']);
        $this->assertTrue($membership['is_auto_renewing']);
        $this->assertSame('ACTIVE', $membership['recurring_status']);
    }

    public function test_memberships_without_subscription(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $result = $this->query->execute($user);

        $membership = $result['memberships'][0];
        $this->assertNull($membership['subscription_id']);
        $this->assertNull($membership['expires_at']);
        $this->assertFalse($membership['is_recurring']);
        $this->assertFalse($membership['is_auto_renewing']);
    }

    public function test_returns_default_notification_prefs(): void
    {
        $user = User::factory()->create();
        $result = $this->query->execute($user);

        $this->assertTrue($result['notifPrefs']['follower']);
        $this->assertTrue($result['notifPrefs']['likes']);
        $this->assertTrue($result['notifPrefs']['kaching']);
        $this->assertTrue($result['notifPrefs']['affiliate']);
    }

    public function test_returns_merged_notification_prefs(): void
    {
        $user = User::factory()->create([
            'notification_prefs' => ['follower' => false],
        ]);
        $result = $this->query->execute($user);

        $this->assertFalse($result['notifPrefs']['follower']);
        $this->assertTrue($result['notifPrefs']['likes']); // default preserved
    }

    public function test_returns_default_chat_prefs(): void
    {
        $user = User::factory()->create();
        $result = $this->query->execute($user);

        $this->assertTrue($result['chatPrefs']['notifications']);
        $this->assertTrue($result['chatPrefs']['email_notifications']);
    }

    public function test_returns_affiliate_link(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);
        $result = $this->query->execute($user);

        $this->assertStringContainsString('/register?ref=testuser', $result['affiliateLink']);
    }

    public function test_returns_default_timezone_and_theme(): void
    {
        $user = User::factory()->create();
        $result = $this->query->execute($user);

        $this->assertSame('Asia/Manila', $result['timezone']);
        $this->assertSame('light', $result['theme']);
    }

    public function test_returns_kyc_data(): void
    {
        $user = User::factory()->create();
        $result = $this->query->execute($user);

        $this->assertArrayHasKey('kyc', $result);
        $this->assertSame('none', $result['kyc']['status']);
        $this->assertSame(0, $result['kyc']['ai_rejections']);
    }

    public function test_returns_payout_data(): void
    {
        $user = User::factory()->create([
            'payout_method' => 'bank',
            'payout_details' => '1234567890',
            'bank_name' => 'BDO',
        ]);
        $result = $this->query->execute($user);

        $this->assertSame('bank', $result['payoutMethod']);
        $this->assertSame('1234567890', $result['payoutDetails']);
        $this->assertSame('BDO', $result['bankName']);
    }

    public function test_membership_marks_owner_correctly(): void
    {
        $user = User::factory()->create();
        $ownedCommunity = Community::factory()->create(['owner_id' => $user->id]);
        $otherCommunity = Community::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $ownedCommunity->id,
            'user_id' => $user->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $otherCommunity->id,
            'user_id' => $user->id,
        ]);

        $result = $this->query->execute($user);

        $memberships = $result['memberships']->toArray();
        $owned = collect($memberships)->firstWhere('community_id', $ownedCommunity->id);
        $other = collect($memberships)->firstWhere('community_id', $otherCommunity->id);

        $this->assertTrue($owned['is_owner']);
        $this->assertFalse($other['is_owner']);
    }

    public function test_community_notif_prefs_have_defaults(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $result = $this->query->execute($user);
        $notif = $result['memberships'][0]['notif_prefs'];

        $this->assertTrue($notif['new_posts']);
        $this->assertTrue($notif['comments']);
        $this->assertTrue($notif['mentions']);
    }

    public function test_single_name_user_has_empty_last_name(): void
    {
        $user = User::factory()->create(['name' => 'Madonna']);
        $result = $this->query->execute($user);

        $this->assertSame('Madonna', $result['profileUser']['first_name']);
        // explode with limit 2 on single word returns array with 1 element; index [1] uses ?? ''
        $this->assertSame('', $result['profileUser']['last_name']);
    }
}
