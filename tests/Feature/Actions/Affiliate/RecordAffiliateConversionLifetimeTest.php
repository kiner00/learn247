<?php

namespace Tests\Feature\Actions\Affiliate;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecordAffiliateConversionLifetimeTest extends TestCase
{
    use RefreshDatabase;

    private RecordAffiliateConversion $action;

    protected function setUp(): void
    {
        parent::setUp();

        $badge = Mockery::mock(BadgeService::class);
        $badge->shouldReceive('evaluate')->andReturnNull();
        $this->app->instance(BadgeService::class, $badge);

        $this->action = app(RecordAffiliateConversion::class);
    }

    public function test_lifetime_on_pays_first_affiliate_even_when_second_link_used(): void
    {
        $community = Community::factory()->create([
            'affiliate_commission_rate' => 10,
            'lifetime_attribution' => true,
        ]);

        $aff1 = $this->newSubscribedAffiliate($community);
        $aff2 = $this->newSubscribedAffiliate($community);
        $referred = User::factory()->create();

        $courseA = Course::factory()->create([
            'community_id' => $community->id, 'price' => 500, 'affiliate_commission_rate' => 20,
        ]);
        $courseB = Course::factory()->create([
            'community_id' => $community->id, 'price' => 500, 'affiliate_commission_rate' => 20,
        ]);

        $this->action->executeForCourse($this->enrollViaAffiliate($referred, $courseA, $aff1));
        $this->action->executeForCourse($this->enrollViaAffiliate($referred, $courseB, $aff2));

        $conversions = AffiliateConversion::orderBy('id')->get();
        $this->assertCount(2, $conversions);
        $this->assertEquals($aff1->id, $conversions[0]->affiliate_id);
        $this->assertEquals($aff1->id, $conversions[1]->affiliate_id, 'second purchase must be credited to the first affiliate under lifetime mode');
        $this->assertFalse($conversions[0]->is_lifetime);
        $this->assertTrue($conversions[1]->is_lifetime);
    }

    public function test_lifetime_off_uses_last_touch(): void
    {
        $community = Community::factory()->create([
            'affiliate_commission_rate' => 10,
            'lifetime_attribution' => false,
        ]);

        $aff1 = $this->newSubscribedAffiliate($community);
        $aff2 = $this->newSubscribedAffiliate($community);
        $referred = User::factory()->create();

        $courseA = Course::factory()->create([
            'community_id' => $community->id, 'price' => 500, 'affiliate_commission_rate' => 20,
        ]);
        $courseB = Course::factory()->create([
            'community_id' => $community->id, 'price' => 500, 'affiliate_commission_rate' => 20,
        ]);

        $this->action->executeForCourse($this->enrollViaAffiliate($referred, $courseA, $aff1));
        $this->action->executeForCourse($this->enrollViaAffiliate($referred, $courseB, $aff2));

        $conversions = AffiliateConversion::orderBy('id')->get();
        $this->assertEquals($aff1->id, $conversions[0]->affiliate_id);
        $this->assertEquals($aff2->id, $conversions[1]->affiliate_id);
        $this->assertFalse($conversions[0]->is_lifetime);
        $this->assertFalse($conversions[1]->is_lifetime);
    }

    public function test_conversion_emits_wallet_credit_at_paid_with_hold(): void
    {
        config(['affiliate.hold_days' => 7]);

        $community = Community::factory()->create([
            'affiliate_commission_rate' => 10,
            'lifetime_attribution' => true,
        ]);
        $aff = $this->newSubscribedAffiliate($community);
        $referred = User::factory()->create();

        $course = Course::factory()->create([
            'community_id' => $community->id,
            'price' => 500,
            'affiliate_commission_rate' => 20,
        ]);

        $this->action->executeForCourse($this->enrollViaAffiliate($referred, $course, $aff));

        $tx = WalletTransaction::where('user_id', $aff->user_id)->first();
        $this->assertNotNull($tx, 'expected a wallet transaction for the affiliate user');
        $this->assertEquals(WalletTransaction::TYPE_CREDIT, $tx->type);
        $this->assertEquals(WalletTransaction::STATUS_PAID, $tx->status);
        $this->assertEquals(100.0, (float) $tx->amount);
        $this->assertNotNull($tx->available_at);
        $this->assertTrue($tx->available_at->greaterThan(now()->addDays(6)));

        $wallet = Wallet::where('user_id', $aff->user_id)->first();
        $this->assertEquals(100.0, (float) $wallet->pending_balance);
        $this->assertEquals(0.0, (float) $wallet->balance);
    }

    public function test_lapsed_affiliate_blocks_lifetime_payout(): void
    {
        $community = Community::factory()->create([
            'affiliate_commission_rate' => 10,
            'lifetime_attribution' => true,
        ]);

        $aff = $this->newSubscribedAffiliate($community);
        $referred = User::factory()->create();

        $courseA = Course::factory()->create([
            'community_id' => $community->id, 'price' => 500, 'affiliate_commission_rate' => 20,
        ]);
        $courseB = Course::factory()->create([
            'community_id' => $community->id, 'price' => 500, 'affiliate_commission_rate' => 20,
        ]);

        $this->action->executeForCourse($this->enrollViaAffiliate($referred, $courseA, $aff));

        \App\Models\Subscription::where('user_id', $aff->user_id)
            ->where('community_id', $community->id)
            ->update(['status' => 'inactive', 'expires_at' => now()->subDay()]);

        $this->action->executeForCourse($this->enrollViaAffiliate($referred, $courseB, null));

        $this->assertEquals(1, AffiliateConversion::count(), 'no commission should record while affiliate is lapsed');
    }

    private function newSubscribedAffiliate(Community $community): Affiliate
    {
        $user = User::factory()->create();
        \App\Models\Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        return Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'L'.uniqid(),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
    }

    private function enrollViaAffiliate(User $user, Course $course, ?Affiliate $affiliate): CourseEnrollment
    {
        return CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'affiliate_id' => $affiliate?->id,
            'status' => 'active',
            'paid_at' => now(),
        ]);
    }
}
