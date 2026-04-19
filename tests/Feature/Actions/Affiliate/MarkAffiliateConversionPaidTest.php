<?php

namespace Tests\Feature\Actions\Affiliate;

use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAffiliateConversionPaidTest extends TestCase
{
    use RefreshDatabase;

    private MarkAffiliateConversionPaid $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new MarkAffiliateConversionPaid;
    }

    private function createConversion(Affiliate $affiliate, float $commission = 50): AffiliateConversion
    {
        $sub = Subscription::factory()->active()->create([
            'community_id' => $affiliate->community_id,
            'user_id' => User::factory()->create()->id,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $affiliate->community_id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        return AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => $commission,
            'creator_amount' => 500 - 75 - $commission,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);
    }

    public function test_marks_conversion_as_paid_with_timestamp(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'PAID001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        $conversion = $this->createConversion($affiliate);

        $this->action->execute($conversion);

        $conversion->refresh();
        $this->assertEquals(AffiliateConversion::STATUS_PAID, $conversion->status);
        $this->assertNotNull($conversion->paid_at);
    }

    public function test_increments_affiliate_total_paid(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'PAID002',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_paid' => 0,
        ]);
        $conversion = $this->createConversion($affiliate, 80);

        $this->action->execute($conversion);

        $affiliate->refresh();
        $this->assertEquals(80, (float) $affiliate->total_paid);
    }
}
