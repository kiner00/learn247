<?php

namespace Tests\Feature\Actions\Affiliate;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecordAffiliateConversionTest extends TestCase
{
    use RefreshDatabase;

    private RecordAffiliateConversion $action;

    protected function setUp(): void
    {
        parent::setUp();

        $badge = Mockery::mock(BadgeService::class);
        $badge->shouldReceive('evaluate')->andReturnNull();
        $this->app->instance(BadgeService::class, $badge);

        $this->action = new RecordAffiliateConversion();
    }

    public function test_records_conversion_with_correct_amounts(): void
    {
        $affiliateUser = User::factory()->create();
        $community     = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate     = Affiliate::create([
            'user_id'      => $affiliateUser->id,
            'community_id' => $community->id,
            'code'         => 'REC001',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
        ]);

        $referredUser  = User::factory()->create();
        $subscription  = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referredUser->id,
            'affiliate_id' => $affiliate->id,
        ]);
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $referredUser->id,
            'amount'          => 1000,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now(),
        ]);

        $this->action->execute($subscription, $payment);

        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id'     => $affiliate->id,
            'subscription_id'  => $subscription->id,
            'payment_id'       => $payment->id,
            'referred_user_id' => $referredUser->id,
            'sale_amount'      => 1000,
            'platform_fee'     => 150,
            'commission_amount' => 100,
            'creator_amount'   => 750,
            'status'           => AffiliateConversion::STATUS_PENDING,
        ]);
    }

    public function test_increments_affiliate_total_earned(): void
    {
        $affiliateUser = User::factory()->create();
        $community     = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate     = Affiliate::create([
            'user_id'      => $affiliateUser->id,
            'community_id' => $community->id,
            'code'         => 'REC002',
            'status'       => Affiliate::STATUS_ACTIVE,
            'total_earned' => 0,
        ]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
        ]);

        $referredUser = User::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referredUser->id,
            'affiliate_id' => $affiliate->id,
        ]);
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $referredUser->id,
            'amount'          => 1000,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now(),
        ]);

        $this->action->execute($subscription, $payment);

        $affiliate->refresh();
        $this->assertEquals(100, (float) $affiliate->total_earned);
    }

    public function test_skips_if_subscription_has_no_affiliate(): void
    {
        $referredUser = User::factory()->create();
        $community    = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referredUser->id,
            'affiliate_id' => null,
        ]);
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $referredUser->id,
            'amount'          => 1000,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now(),
        ]);

        $this->action->execute($subscription, $payment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    public function test_skips_if_affiliate_is_not_subscribed(): void
    {
        $affiliateUser = User::factory()->create();
        $community     = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate     = Affiliate::create([
            'user_id'      => $affiliateUser->id,
            'community_id' => $community->id,
            'code'         => 'REC003',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $referredUser = User::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referredUser->id,
            'affiliate_id' => $affiliate->id,
        ]);
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $referredUser->id,
            'amount'          => 1000,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now(),
        ]);

        $this->action->execute($subscription, $payment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    public function test_math_500_sale_20_percent_rate(): void
    {
        $affiliateUser = User::factory()->create();
        $community     = Community::factory()->create(['affiliate_commission_rate' => 20]);
        $affiliate     = Affiliate::create([
            'user_id'      => $affiliateUser->id,
            'community_id' => $community->id,
            'code'         => 'REC004',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
        ]);

        $referredUser = User::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $referredUser->id,
            'affiliate_id' => $affiliate->id,
        ]);
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $referredUser->id,
            'amount'          => 500,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now(),
        ]);

        $this->action->execute($subscription, $payment);

        $this->assertDatabaseHas('affiliate_conversions', [
            'sale_amount'       => 500,
            'platform_fee'      => 75,
            'commission_amount' => 100,
            'creator_amount'    => 325,
        ]);
    }
}
