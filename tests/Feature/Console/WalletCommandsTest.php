<?php

namespace Tests\Feature\Console;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settle_due_promotes_paid_transactions_past_hold(): void
    {
        $user = User::factory()->create();
        $service = app(WalletService::class);

        $due = $service->credit($user, $this->fakeSource(), 50, WalletTransaction::STATUS_PAID, now()->subDay());
        $notDue = $service->credit($user, $this->fakeSource(), 80, WalletTransaction::STATUS_PAID, now()->addDays(7));

        $this->artisan('wallet:settle-due')
            ->expectsOutputToContain('Settled 1 wallet transaction(s).')
            ->assertSuccessful();

        $this->assertEquals(WalletTransaction::STATUS_SETTLED, $due->fresh()->status);
        $this->assertEquals(WalletTransaction::STATUS_PAID, $notDue->fresh()->status);

        $wallet = Wallet::where('user_id', $user->id)->first();
        $this->assertEquals(50.0, (float) $wallet->balance);
        $this->assertEquals(80.0, (float) $wallet->pending_balance);
    }

    public function test_reconcile_reports_clean_wallets(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, $this->fakeSource(), 100, WalletTransaction::STATUS_SETTLED);

        $this->artisan('wallet:reconcile')
            ->expectsOutputToContain('All wallets reconciled cleanly.')
            ->assertSuccessful();
    }

    public function test_reconcile_detects_and_fixes_drift(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, $this->fakeSource(), 100, WalletTransaction::STATUS_SETTLED);

        $wallet = Wallet::where('user_id', $user->id)->first();
        $wallet->update(['balance' => 999.99]);

        $this->artisan('wallet:reconcile --fix')
            ->expectsOutputToContain('Found 1 wallet(s) with drift.')
            ->assertSuccessful();

        $this->assertEquals(100.0, (float) $wallet->fresh()->balance);
    }

    public function test_reconcile_without_fix_leaves_drift_in_place(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, $this->fakeSource(), 100, WalletTransaction::STATUS_SETTLED);

        $wallet = Wallet::where('user_id', $user->id)->first();
        $wallet->update(['balance' => 999.99]);

        $this->artisan('wallet:reconcile')->assertSuccessful();

        $this->assertEquals(999.99, (float) $wallet->fresh()->balance);
    }

    private function fakeSource(): AffiliateConversion
    {
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'WC'.uniqid(),
            'status' => Affiliate::STATUS_ACTIVE,
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
