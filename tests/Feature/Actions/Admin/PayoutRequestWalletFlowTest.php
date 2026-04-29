<?php

namespace Tests\Feature\Actions\Admin;

use App\Actions\Admin\ApprovePayoutRequest;
use App\Actions\Admin\RejectPayoutRequest;
use App\Actions\Payout\RequestOwnerPayout;
use App\Models\Community;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PayoutRequestWalletFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_request_locks_balance_via_pending_debit(): void
    {
        [$owner, $community] = $this->ownerWithBalance(2000);

        $result = app(RequestOwnerPayout::class)->execute($owner, $community, 1000);

        $this->assertTrue($result['success']);
        $request = PayoutRequest::first();
        $debit = WalletTransaction::where('source_type', $request->getMorphClass())
            ->where('source_id', $request->id)
            ->where('type', WalletTransaction::TYPE_DEBIT)
            ->first();

        $this->assertNotNull($debit);
        $this->assertEquals(WalletTransaction::STATUS_PENDING, $debit->status);

        $wallet = Wallet::where('user_id', $owner->id)->first();
        $this->assertEquals(1000.0, (float) $wallet->balance);
    }

    public function test_approval_transitions_pending_debit_to_withdrawn(): void
    {
        [$owner, $community] = $this->ownerWithBalance(2000);
        app(RequestOwnerPayout::class)->execute($owner, $community, 1000);

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('createPayout')->andReturn(['id' => 'po_123']);
        $this->app->instance(XenditService::class, $xendit);

        $this->actingAs($owner);
        app(ApprovePayoutRequest::class)->execute(PayoutRequest::first());

        $debit = WalletTransaction::where('type', WalletTransaction::TYPE_DEBIT)->first();
        $this->assertEquals(WalletTransaction::STATUS_WITHDRAWN, $debit->status);
        $this->assertNotNull($debit->withdrawn_at);

        $wallet = Wallet::where('user_id', $owner->id)->first();
        $this->assertEquals(1000.0, (float) $wallet->balance, 'balance should not change again on approval');
    }

    public function test_rejection_reverses_pending_debit_and_restores_balance(): void
    {
        [$owner, $community] = $this->ownerWithBalance(2000);
        app(RequestOwnerPayout::class)->execute($owner, $community, 1000);

        $this->actingAs($owner);
        app(RejectPayoutRequest::class)->execute(PayoutRequest::first(), 'Insufficient docs');

        $debit = WalletTransaction::where('type', WalletTransaction::TYPE_DEBIT)->first();
        $this->assertEquals(WalletTransaction::STATUS_REVERSED, $debit->status);

        $wallet = Wallet::where('user_id', $owner->id)->first();
        $this->assertEquals(2000.0, (float) $wallet->balance, 'balance should be restored after rejection');
    }

    /**
     * @return array{0: User, 1: Community}
     */
    private function ownerWithBalance(float $amount): array
    {
        $owner = User::factory()->create([
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
            'kyc_verified_at' => now(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $sub = \App\Models\Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $payment = \App\Models\Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $sub->user_id,
            'amount' => $amount + 200,
            'platform_fee' => 200,
            'currency' => 'PHP',
            'status' => \App\Models\Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now()->subDays(20),
        ]);

        $tx = app(WalletService::class)->credit(
            $owner,
            $payment,
            $amount,
            WalletTransaction::STATUS_SETTLED,
        );
        $this->assertEquals(WalletTransaction::STATUS_SETTLED, $tx->fresh()->status);

        return [$owner, $community];
    }
}
