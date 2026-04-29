<?php

namespace Tests\Feature\Services\Wallet;

use App\Models\AffiliateConversion;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WalletService;
    }

    public function test_credit_at_paid_increments_pending_balance_only(): void
    {
        $user = User::factory()->create();
        $source = $this->fakeSource();

        $tx = $this->service->credit($user, $source, 100.00, WalletTransaction::STATUS_PAID, now()->addDays(7));

        $this->assertEquals(WalletTransaction::STATUS_PAID, $tx->status);
        $this->assertEquals(0.0, $this->service->balanceOf($user)['balance']);
        $this->assertEquals(100.0, $this->service->balanceOf($user)['pending_balance']);
    }

    public function test_credit_at_settled_increments_balance(): void
    {
        $user = User::factory()->create();
        $tx = $this->service->credit($user, $this->fakeSource(), 50.00, WalletTransaction::STATUS_SETTLED);

        $this->assertEquals(50.0, $this->service->balanceOf($user)['balance']);
        $this->assertEquals(0.0, $this->service->balanceOf($user)['pending_balance']);
    }

    public function test_paid_to_settled_moves_balance_buckets(): void
    {
        $user = User::factory()->create();
        $tx = $this->service->credit($user, $this->fakeSource(), 100.00, WalletTransaction::STATUS_PAID, now()->addDays(7));

        $this->service->transition($tx, WalletTransaction::STATUS_SETTLED);

        $balance = $this->service->balanceOf($user);
        $this->assertEquals(100.0, $balance['balance']);
        $this->assertEquals(0.0, $balance['pending_balance']);
    }

    public function test_credit_idempotent_on_same_source(): void
    {
        $user = User::factory()->create();
        $source = $this->fakeSource();

        $first = $this->service->credit($user, $source, 100.00, WalletTransaction::STATUS_PAID, now()->addDays(7));
        $second = $this->service->credit($user, $source, 100.00, WalletTransaction::STATUS_PAID, now()->addDays(7));

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(100.0, $this->service->balanceOf($user)['pending_balance']);
    }

    public function test_settle_due_promotes_only_due_paid_transactions(): void
    {
        $user = User::factory()->create();

        $due = $this->service->credit($user, $this->fakeSource(), 30.00, WalletTransaction::STATUS_PAID, now()->subDay());
        $notDue = $this->service->credit($user, $this->fakeSource(), 70.00, WalletTransaction::STATUS_PAID, now()->addDays(7));

        $promoted = $this->service->settleDue();

        $this->assertEquals(1, $promoted);
        $this->assertEquals(WalletTransaction::STATUS_SETTLED, $due->fresh()->status);
        $this->assertEquals(WalletTransaction::STATUS_PAID, $notDue->fresh()->status);

        $balance = $this->service->balanceOf($user);
        $this->assertEquals(30.0, $balance['balance']);
        $this->assertEquals(70.0, $balance['pending_balance']);
    }

    public function test_debit_decrements_balance_and_blocks_if_insufficient(): void
    {
        $user = User::factory()->create();
        $this->service->credit($user, $this->fakeSource(), 50.00, WalletTransaction::STATUS_SETTLED);

        $debit = $this->service->debit($user, $this->fakeSource(), 30.00);
        $this->assertEquals(20.0, $this->service->balanceOf($user)['balance']);
        $this->assertEquals(WalletTransaction::STATUS_WITHDRAWN, $debit->status);
        $this->assertNotNull($debit->withdrawn_at);

        $this->expectException(RuntimeException::class);
        $this->service->debit($user, $this->fakeSource(), 999.00);
    }

    public function test_credit_amount_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->credit(User::factory()->create(), $this->fakeSource(), 0, WalletTransaction::STATUS_PAID);
    }

    public function test_illegal_transition_is_rejected(): void
    {
        $user = User::factory()->create();
        $tx = $this->service->credit($user, $this->fakeSource(), 100.00, WalletTransaction::STATUS_SETTLED);

        $this->expectException(InvalidArgumentException::class);
        $this->service->transition($tx, WalletTransaction::STATUS_PAID);
    }

    private function fakeSource(): AffiliateConversion
    {
        $community = \App\Models\Community::factory()->create();
        $affiliate = \App\Models\Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'WT'.uniqid(),
            'status' => \App\Models\Affiliate::STATUS_ACTIVE,
        ]);

        return AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'referred_user_id' => User::factory()->create()->id,
            'sale_amount' => 100,
            'platform_fee' => 0,
            'commission_amount' => 100,
            'creator_amount' => 0,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);
    }
}
