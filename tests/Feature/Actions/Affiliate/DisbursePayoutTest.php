<?php

namespace Tests\Feature\Actions\Affiliate;

use App\Actions\Affiliate\DisbursePayout;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class DisbursePayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_supports_gcash(): void
    {
        $this->assertTrue(DisbursePayout::supports('gcash'));
    }

    public function test_supports_maya(): void
    {
        $this->assertTrue(DisbursePayout::supports('maya'));
    }

    public function test_does_not_support_bank(): void
    {
        $this->assertFalse(DisbursePayout::supports('bank'));
    }

    public function test_does_not_support_paypal(): void
    {
        $this->assertFalse(DisbursePayout::supports('paypal'));
    }

    public function test_execute_calls_xendit_and_returns_payout_data(): void
    {
        $user = User::factory()->create([
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
        ]);
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'code' => 'DIS001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $sub = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $sub->user_id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $expectedResponse = [
            'id' => 'payout_abc123',
            'reference_id' => 'payout-'.$conversion->id,
            'status' => 'ACCEPTED',
            'amount' => 50,
        ];

        $this->mock(XenditService::class, function (MockInterface $mock) use ($expectedResponse) {
            $mock->shouldReceive('createPayout')
                ->once()
                ->withArgs(function (array $data) {
                    return $data['channel_code'] === 'PH_GCASH'
                        && $data['amount'] === 50.0
                        && $data['currency'] === 'PHP';
                })
                ->andReturn($expectedResponse);
        });

        $action = app(DisbursePayout::class);
        $result = $action->execute($conversion);

        $this->assertEquals($expectedResponse, $result);
    }
}
