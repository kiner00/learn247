<?php

namespace Tests\Feature\Actions\Payout;

use App\Actions\Payout\RecordCreatorEarning;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordCreatorEarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_credits_owner_with_net_minus_platform_fee(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $payment = $this->fakePayment($community, gross: 1000, platformFee: 98);

        app(RecordCreatorEarning::class)->execute($payment);

        $wallet = Wallet::where('user_id', $owner->id)->first();
        $this->assertEquals(902.0, (float) $wallet->pending_balance);
        $this->assertEquals(0.0, (float) $wallet->balance);
    }

    public function test_credit_amount_subtracts_affiliate_commission(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'affiliate_commission_rate' => 10,
        ]);
        $payment = $this->fakePayment($community, gross: 1000, platformFee: 98);

        $affUser = User::factory()->create();
        $affiliate = Affiliate::create([
            'user_id' => $affUser->id,
            'community_id' => $community->id,
            'code' => 'OW001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'payment_id' => $payment->id,
            'subscription_id' => $payment->subscription_id,
            'referred_user_id' => $payment->user_id,
            'sale_amount' => 1000,
            'platform_fee' => 98,
            'commission_amount' => 100,
            'creator_amount' => 802,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        app(RecordCreatorEarning::class)->execute($payment);

        $wallet = Wallet::where('user_id', $owner->id)->first();
        $this->assertEquals(802.0, (float) $wallet->pending_balance);
    }

    public function test_idempotent_on_same_payment(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $payment = $this->fakePayment($community, gross: 500, platformFee: 49);

        app(RecordCreatorEarning::class)->execute($payment);
        app(RecordCreatorEarning::class)->execute($payment);

        $this->assertEquals(
            1,
            WalletTransaction::where('source_id', $payment->id)
                ->where('source_type', $payment->getMorphClass())
                ->count(),
        );
    }

    public function test_skips_unpaid_payments(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $payment = $this->fakePayment($community, gross: 500, platformFee: 49, status: Payment::STATUS_PENDING);

        app(RecordCreatorEarning::class)->execute($payment);

        $this->assertEquals(0, WalletTransaction::count());
    }

    private function fakePayment(Community $community, float $gross, float $platformFee, string $status = Payment::STATUS_PAID): Payment
    {
        $sub = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
        ]);

        return Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => $gross,
            'platform_fee' => $platformFee,
            'currency' => 'PHP',
            'status' => $status,
            'metadata' => [],
            'paid_at' => $status === Payment::STATUS_PAID ? now() : null,
        ]);
    }
}
