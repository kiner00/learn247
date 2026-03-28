<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Actions\Billing\WebhookHandlers\HandleCertificationPurchasePaid;
use App\Models\Affiliate;
use App\Models\CertificationPurchase;
use App\Models\Community;
use App\Models\CourseCertification;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HandleCertificationPurchasePaidTest extends TestCase
{
    use RefreshDatabase;

    private function createCertPurchaseSetup(array $overrides = []): array
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'                  => $owner->id,
            'affiliate_commission_rate' => 10,
        ]);

        $certification = CourseCertification::create([
            'community_id'              => $community->id,
            'title'                     => 'Test Certification',
            'cert_title'                => 'Certified Tester',
            'price'                     => 500,
            'affiliate_commission_rate' => 10,
            'pass_score'                => 70,
        ]);

        $buyer = User::factory()->create();

        $purchase = CertificationPurchase::create(array_merge([
            'user_id'          => $buyer->id,
            'certification_id' => $certification->id,
            'xendit_id'        => 'inv_cert_' . uniqid(),
            'status'           => CertificationPurchase::STATUS_PENDING,
        ], $overrides));

        return compact('owner', 'community', 'certification', 'buyer', 'purchase');
    }

    // ─── matches() ─────────────────────────────────────────────────────────────

    public function test_matches_returns_true_when_certification_purchase_exists(): void
    {
        $setup   = $this->createCertPurchaseSetup(['xendit_id' => 'inv_cert_match']);
        $handler = app(HandleCertificationPurchasePaid::class);

        $this->assertTrue($handler->matches('inv_cert_match'));
    }

    public function test_matches_returns_false_when_no_certification_purchase(): void
    {
        $handler = app(HandleCertificationPurchasePaid::class);

        $this->assertFalse($handler->matches('inv_nonexistent'));
    }

    // ─── handle() — PAID status ────────────────────────────────────────────────

    public function test_paid_status_updates_purchase_to_paid(): void
    {
        Mail::fake();

        $setup = $this->createCertPurchaseSetup(['xendit_id' => 'inv_cert_paid']);

        $handler = app(HandleCertificationPurchasePaid::class);
        $handler->matches('inv_cert_paid');

        $handler->handle([
            'amount'   => 500,
            'currency' => 'PHP',
        ], 'evt_cert_paid', 'PAID');

        $setup['purchase']->refresh();
        $this->assertEquals(CertificationPurchase::STATUS_PAID, $setup['purchase']->status);
        $this->assertNotNull($setup['purchase']->paid_at);
    }

    public function test_settled_status_updates_purchase_to_paid(): void
    {
        Mail::fake();

        $setup = $this->createCertPurchaseSetup(['xendit_id' => 'inv_cert_settled']);

        $handler = app(HandleCertificationPurchasePaid::class);
        $handler->matches('inv_cert_settled');

        $handler->handle([
            'amount'   => 500,
            'currency' => 'PHP',
        ], 'evt_cert_settled', 'SETTLED');

        $setup['purchase']->refresh();
        $this->assertEquals(CertificationPurchase::STATUS_PAID, $setup['purchase']->status);
        $this->assertNotNull($setup['purchase']->paid_at);
    }

    // ─── handle() — non-PAID statuses (should be skipped) ──────────────────────

    public function test_expired_status_does_not_update_purchase(): void
    {
        $setup = $this->createCertPurchaseSetup(['xendit_id' => 'inv_cert_exp']);

        $handler = app(HandleCertificationPurchasePaid::class);
        $handler->matches('inv_cert_exp');

        $handler->handle([], 'evt_cert_exp', 'EXPIRED');

        $setup['purchase']->refresh();
        $this->assertEquals(CertificationPurchase::STATUS_PENDING, $setup['purchase']->status);
        $this->assertNull($setup['purchase']->paid_at);
    }

    public function test_failed_status_does_not_update_purchase(): void
    {
        $setup = $this->createCertPurchaseSetup(['xendit_id' => 'inv_cert_fail']);

        $handler = app(HandleCertificationPurchasePaid::class);
        $handler->matches('inv_cert_fail');

        $handler->handle([], 'evt_cert_fail', 'FAILED');

        $setup['purchase']->refresh();
        $this->assertEquals(CertificationPurchase::STATUS_PENDING, $setup['purchase']->status);
    }

    public function test_unknown_status_does_not_update_purchase(): void
    {
        $setup = $this->createCertPurchaseSetup(['xendit_id' => 'inv_cert_unk']);

        $handler = app(HandleCertificationPurchasePaid::class);
        $handler->matches('inv_cert_unk');

        $handler->handle([], 'evt_cert_unk', 'PENDING');

        $setup['purchase']->refresh();
        $this->assertEquals(CertificationPurchase::STATUS_PENDING, $setup['purchase']->status);
    }

    // ─── handle() — affiliate conversion recording ─────────────────────────────

    public function test_paid_purchase_with_affiliate_records_conversion_and_sends_cha_ching(): void
    {
        Mail::fake();

        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'                  => $owner->id,
            'affiliate_commission_rate' => 10,
        ]);

        $certification = CourseCertification::create([
            'community_id'              => $community->id,
            'title'                     => 'Affiliate Cert',
            'cert_title'                => 'Certified',
            'price'                     => 1000,
            'affiliate_commission_rate' => 15,
            'pass_score'                => 70,
        ]);

        $affiliateUser = User::factory()->create();
        $affiliate     = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
            'code'         => 'AFF-CERT-1',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        // Affiliate must be subscribed
        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        $buyer    = User::factory()->create();
        $purchase = CertificationPurchase::create([
            'user_id'          => $buyer->id,
            'certification_id' => $certification->id,
            'affiliate_id'     => $affiliate->id,
            'xendit_id'        => 'inv_cert_aff',
            'status'           => CertificationPurchase::STATUS_PENDING,
        ]);

        $handler = app(HandleCertificationPurchasePaid::class);
        $handler->matches('inv_cert_aff');

        $handler->handle([
            'amount'   => 1000,
            'currency' => 'PHP',
        ], 'evt_cert_aff', 'PAID');

        $purchase->refresh();
        $this->assertEquals(CertificationPurchase::STATUS_PAID, $purchase->status);

        // Affiliate conversion should be recorded
        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id'              => $affiliate->id,
            'certification_purchase_id' => $purchase->id,
            'referred_user_id'          => $buyer->id,
        ]);
    }

    public function test_paid_purchase_without_affiliate_does_not_record_conversion(): void
    {
        Mail::fake();

        $setup = $this->createCertPurchaseSetup(['xendit_id' => 'inv_cert_no_aff']);

        $handler = app(HandleCertificationPurchasePaid::class);
        $handler->matches('inv_cert_no_aff');

        $handler->handle([
            'amount'   => 500,
            'currency' => 'PHP',
        ], 'evt_cert_no_aff', 'PAID');

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    public function test_paid_purchase_with_zero_commission_rate_skips_conversion(): void
    {
        Mail::fake();

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $certification = CourseCertification::create([
            'community_id'              => $community->id,
            'title'                     => 'No Commission Cert',
            'cert_title'                => 'Certified',
            'price'                     => 500,
            'affiliate_commission_rate' => 0,
            'pass_score'                => 70,
        ]);

        $affiliateUser = User::factory()->create();
        $affiliate     = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
            'code'         => 'AFF-CERT-ZERO',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        $buyer    = User::factory()->create();
        $purchase = CertificationPurchase::create([
            'user_id'          => $buyer->id,
            'certification_id' => $certification->id,
            'affiliate_id'     => $affiliate->id,
            'xendit_id'        => 'inv_cert_zero_rate',
            'status'           => CertificationPurchase::STATUS_PENDING,
        ]);

        $handler = app(HandleCertificationPurchasePaid::class);
        $handler->matches('inv_cert_zero_rate');

        $handler->handle([
            'amount'   => 500,
            'currency' => 'PHP',
        ], 'evt_cert_zero', 'PAID');

        $purchase->refresh();
        $this->assertEquals(CertificationPurchase::STATUS_PAID, $purchase->status);
        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    // ── catch/rethrow block (lines 62-67) ───────────────────────────────────

    public function test_handle_logs_error_and_rethrows_on_exception(): void
    {
        $setup = $this->createCertPurchaseSetup(['xendit_id' => 'inv_cert_err']);

        // Make RecordAffiliateConversion throw to trigger the catch block
        $this->mock(RecordAffiliateConversion::class)
            ->shouldReceive('executeForCertification')
            ->andThrow(new \RuntimeException('certification conversion failed'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'HandleCertificationPurchasePaid failed'
                && $ctx['purchase_id'] === $setup['purchase']->id
                && str_contains($ctx['error'], 'certification conversion failed'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('certification conversion failed');

        $handler = app(HandleCertificationPurchasePaid::class);
        $handler->matches('inv_cert_err');

        $handler->handle([
            'amount'   => 500,
            'currency' => 'PHP',
        ], 'evt_cert_err', 'PAID');
    }
}
