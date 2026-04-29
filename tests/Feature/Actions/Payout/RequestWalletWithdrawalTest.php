<?php

namespace Tests\Feature\Actions\Payout;

use App\Actions\Payout\RequestWalletWithdrawal;
use App\Models\AffiliateConversion;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestWalletWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_withdrawal_creates_request_and_locks_balance(): void
    {
        $user = $this->verifiedUser();
        $this->seedWalletBalance($user, 1500);

        $result = app(RequestWalletWithdrawal::class)->execute($user, 500);

        $this->assertTrue($result['success']);
        $this->assertEquals(PayoutRequest::TYPE_WALLET, PayoutRequest::first()->type);
        $this->assertEquals(1000.0, (float) Wallet::where('user_id', $user->id)->first()->balance);

        $debit = WalletTransaction::where('type', WalletTransaction::TYPE_DEBIT)->first();
        $this->assertEquals(WalletTransaction::STATUS_PENDING, $debit->status);
    }

    public function test_unverified_kyc_blocks_withdrawal(): void
    {
        $user = User::factory()->create([
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
            'kyc_verified_at' => null,
        ]);
        $this->seedWalletBalance($user, 1500);

        $result = app(RequestWalletWithdrawal::class)->execute($user, 500);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('KYC', $result['message']);
        $this->assertEquals(0, PayoutRequest::count());
    }

    public function test_missing_payout_method_blocks_withdrawal(): void
    {
        $user = User::factory()->create([
            'payout_method' => null,
            'payout_details' => null,
            'kyc_verified_at' => now(),
        ]);
        $this->seedWalletBalance($user, 1500);

        $result = app(RequestWalletWithdrawal::class)->execute($user, 500);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('payout method', $result['message']);
    }

    public function test_amount_below_minimum_blocks_withdrawal(): void
    {
        $user = $this->verifiedUser();
        $this->seedWalletBalance($user, 1500);

        $result = app(RequestWalletWithdrawal::class)->execute($user, 50);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Minimum', $result['message']);
    }

    public function test_amount_exceeds_balance_blocks_withdrawal(): void
    {
        $user = $this->verifiedUser();
        $this->seedWalletBalance($user, 200);

        $result = app(RequestWalletWithdrawal::class)->execute($user, 500);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('exceeds wallet balance', $result['message']);
    }

    public function test_open_request_blocks_new_withdrawal(): void
    {
        $user = $this->verifiedUser();
        $this->seedWalletBalance($user, 5000);

        app(RequestWalletWithdrawal::class)->execute($user, 500);
        $second = app(RequestWalletWithdrawal::class)->execute($user, 500);

        $this->assertFalse($second['success']);
        $this->assertStringContainsString('open wallet withdrawal', $second['message']);
        $this->assertEquals(1, PayoutRequest::count());
    }

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
            'kyc_verified_at' => now(),
        ]);
    }

    private function seedWalletBalance(User $user, float $amount): void
    {
        $community = \App\Models\Community::factory()->create();
        $affiliate = \App\Models\Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'WW'.uniqid(),
            'status' => \App\Models\Affiliate::STATUS_ACTIVE,
        ]);
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'referred_user_id' => User::factory()->create()->id,
            'sale_amount' => $amount,
            'platform_fee' => 0,
            'commission_amount' => $amount,
            'creator_amount' => 0,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        app(WalletService::class)->credit($user, $conversion, $amount, WalletTransaction::STATUS_SETTLED);
    }
}
