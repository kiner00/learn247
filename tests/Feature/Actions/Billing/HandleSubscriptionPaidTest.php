<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\SyncMembershipFromSubscription;
use App\Actions\Billing\WebhookHandlers\HandleSubscriptionPaid;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HandleSubscriptionPaidTest extends TestCase
{
    use RefreshDatabase;

    // ── matches() ────────────────────────────────────────────────────────────

    public function test_matches_returns_true_for_existing_subscription(): void
    {
        $community = Community::factory()->create();
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'xendit_id' => 'inv_match_yes',
            'status' => Subscription::STATUS_PENDING,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $this->assertTrue($handler->matches('inv_match_yes'));
    }

    public function test_matches_returns_false_for_nonexistent_xendit_id(): void
    {
        $handler = app(HandleSubscriptionPaid::class);
        $this->assertFalse($handler->matches('inv_does_not_exist'));
    }

    // ── handle() with null subscription (early return) ───────────────────────

    public function test_handle_logs_warning_and_returns_when_no_subscription(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'no matching subscription'));

        $handler = app(HandleSubscriptionPaid::class);
        // Do NOT call matches() so $subscription stays null
        $handler->handle(['id' => 'inv_orphan'], 'evt_1', 'PAID');

        // No payment created
        $this->assertDatabaseCount('payments', 0);
    }

    // ── one-time billing sets null expires_at ────────────────────────────────

    public function test_one_time_billing_sets_null_expires_at(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create(['billing_type' => Community::BILLING_ONE_TIME]);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_onetime_direct',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_onetime_direct');
        $handler->handle(
            ['id' => 'inv_onetime_direct', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_onetime',
            'PAID'
        );

        $subscription->refresh();
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertNull($subscription->expires_at);
    }

    // ── pending deletion keeps current expiry ────────────────────────────────

    public function test_pending_deletion_does_not_extend_expiry(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->paid()->create(['deletion_requested_at' => now()]);
        $existingExpiry = now()->addDays(10);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_pd_keep',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $existingExpiry,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_pd_keep');
        $handler->handle(
            ['id' => 'inv_pd_keep', 'status' => 'PAID', 'amount' => 300, 'currency' => 'PHP'],
            'evt_pd_keep',
            'PAID'
        );

        $subscription->refresh();
        $this->assertTrue(
            abs($subscription->expires_at->diffInSeconds($existingExpiry)) < 5,
            'Pending-deletion community should not renew expiry'
        );
    }

    // ── pending deletion skips cha-ching for affiliate ───────────────────────

    public function test_pending_deletion_skips_cha_ching_for_affiliate_referral(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $community = Community::factory()->paid()->create([
            'owner_id' => $owner->id,
            'affiliate_commission_rate' => 10,
            'deletion_requested_at' => now(),
        ]);

        $affiliateUser = User::factory()->create(['needs_password_setup' => false]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF_PD_SKIP',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'xendit_id' => 'inv_aff_active_pd',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $referredUser = User::factory()->create(['needs_password_setup' => false]);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'affiliate_id' => $affiliate->id,
            'xendit_id' => 'inv_pd_cha',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDays(10),
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_pd_cha');
        $handler->handle(
            ['id' => 'inv_pd_cha', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_pd_cha',
            'PAID'
        );

        // Payment should still be created
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PAID,
        ]);

        // No cha-ching emails sent for pending-deletion (Mail::fake ensures none queued for creator/affiliate cha-ching)
        Mail::assertNothingQueued();
    }

    // ── affiliate referral on active community sends cha-ching ────────────────

    public function test_affiliate_referral_on_active_community_sends_cha_ching(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $community = Community::factory()->paid()->create([
            'owner_id' => $owner->id,
            'affiliate_commission_rate' => 10,
        ]);

        $affiliateUser = User::factory()->create(['needs_password_setup' => false]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF_ACTIVE_CHA',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'xendit_id' => 'inv_aff_active_sub',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $referredUser = User::factory()->create(['needs_password_setup' => false]);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'affiliate_id' => $affiliate->id,
            'xendit_id' => 'inv_aff_cha_active',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_aff_cha_active');
        $handler->handle(
            ['id' => 'inv_aff_cha_active', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_aff_cha_active',
            'PAID'
        );

        // Payment should be created
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PAID,
        ]);

        // Affiliate and creator cha-ching emails should be sent
        Mail::assertQueued(\App\Mail\AffiliateChaChing::class);
        Mail::assertQueued(\App\Mail\CreatorChaChing::class);
    }

    // ── pending deletion skips creator cha-ching for non-affiliate ───────────

    public function test_pending_deletion_skips_creator_cha_ching_without_affiliate(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $community = Community::factory()->paid()->create([
            'owner_id' => $owner->id,
            'deletion_requested_at' => now(),
        ]);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_pd_no_aff_cha',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDays(10),
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_pd_no_aff_cha');
        $handler->handle(
            ['id' => 'inv_pd_no_aff_cha', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_pd_no_aff_cha',
            'PAID'
        );

        // Payment created, but no cha-ching
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PAID,
        ]);

        Mail::assertNothingQueued();
    }

    // ── EXPIRED status maps to expired payment ──────────────────────────────

    public function test_expired_status_creates_expired_payment_record(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_exp_pay',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDay(),
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_exp_pay');
        $handler->handle(
            ['id' => 'inv_exp_pay', 'status' => 'EXPIRED', 'amount' => 500, 'currency' => 'PHP'],
            'evt_exp_pay',
            'EXPIRED'
        );

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_EXPIRED,
        ]);
    }

    // ── no affiliate still sends creator cha-ching ───────────────────────────

    public function test_paid_without_affiliate_sends_creator_cha_ching(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);
        $user = User::factory()->create(['needs_password_setup' => false]);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_no_aff_cha',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_no_aff_cha');
        $handler->handle(
            ['id' => 'inv_no_aff_cha', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_no_aff_cha',
            'PAID'
        );

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PAID,
        ]);

        // Creator cha-ching email should be queued
        Mail::assertQueued(\App\Mail\CreatorChaChing::class);
    }

    // ── guest password flow ──────────────────────────────────────────────────

    public function test_guest_user_gets_temp_password_generated_and_emailed(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => true]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_guest_direct',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_guest_direct');
        $handler->handle(
            ['id' => 'inv_guest_direct', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_guest_direct',
            'PAID'
        );

        Mail::assertQueued(\App\Mail\TempPasswordMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_non_guest_user_does_not_get_temp_password(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_nong',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_nong');
        $handler->handle(
            ['id' => 'inv_nong', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_nong',
            'PAID'
        );

        Mail::assertNotQueued(\App\Mail\TempPasswordMail::class);
    }

    // ── auto-creates affiliate for paid subscriber ───────────────────────────

    public function test_auto_creates_affiliate_code_for_new_paid_subscriber(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_aff_auto_direct',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_aff_auto_direct');
        $handler->handle(
            ['id' => 'inv_aff_auto_direct', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_aff_auto',
            'PAID'
        );

        $this->assertDatabaseHas('affiliates', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
    }

    public function test_skips_affiliate_creation_when_already_exists(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();

        Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'code' => 'EXISTING_AFF',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_aff_exists',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_aff_exists');
        $handler->handle(
            ['id' => 'inv_aff_exists', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_aff_exists',
            'PAID'
        );

        $this->assertEquals(
            1,
            Affiliate::where('community_id', $community->id)->where('user_id', $user->id)->count()
        );
    }

    // ── expired subscription on pending-deletion triggers graceful delete ─────

    public function test_expired_status_on_pending_deletion_community_deletes_community_when_last(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create(['deletion_requested_at' => now()]);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_grace_del',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDay(),
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_grace_del');
        $handler->handle(
            ['id' => 'inv_grace_del', 'status' => 'EXPIRED', 'amount' => 500],
            'evt_grace_del',
            'EXPIRED'
        );

        $this->assertSoftDeleted('communities', ['id' => $community->id]);
    }

    public function test_expired_status_on_pending_deletion_does_not_delete_when_others_active(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $community = Community::factory()->paid()->create(['deletion_requested_at' => now()]);

        // user2 still has an active sub with future expiry
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user2->id,
            'xendit_id' => 'inv_other_active',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user1->id,
            'xendit_id' => 'inv_grace_no_del',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDay(),
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_grace_no_del');
        $handler->handle(
            ['id' => 'inv_grace_no_del', 'status' => 'EXPIRED', 'amount' => 500],
            'evt_grace_no',
            'EXPIRED'
        );

        // Community should NOT be deleted because user2 is still active
        $this->assertDatabaseHas('communities', ['id' => $community->id, 'deleted_at' => null]);
    }

    // ── FAILED status cancels subscription ───────────────────────────────────

    public function test_failed_status_maps_to_cancelled(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_fail_direct',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_fail_direct');
        $handler->handle(
            ['id' => 'inv_fail_direct', 'status' => 'FAILED', 'amount' => 500],
            'evt_fail',
            'FAILED'
        );

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => Subscription::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_FAILED,
        ]);
    }

    // ── PENDING / unknown status skips payment creation ──────────────────────

    public function test_unknown_status_keeps_pending_and_skips_payment(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_unk_direct',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_unk_direct');
        $handler->handle(
            ['id' => 'inv_unk_direct', 'status' => 'PROCESSING', 'amount' => 500],
            'evt_unk',
            'PROCESSING'
        );

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => Subscription::STATUS_PENDING,
        ]);

        $this->assertDatabaseCount('payments', 0);
    }

    // ── early renewal extends from future expiry ─────────────────────────────

    public function test_early_renewal_extends_from_future_expiry(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $futureExpiry = now()->addDays(15);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_renew_direct',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => $futureExpiry,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_renew_direct');
        $handler->handle(
            ['id' => 'inv_renew_direct', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_renew',
            'PAID'
        );

        $subscription->refresh();
        $expectedExpiry = $futureExpiry->copy()->addMonth();
        $this->assertTrue(
            abs($subscription->expires_at->diffInSeconds($expectedExpiry)) < 5
        );
    }

    // ── late renewal (expired) starts from now ───────────────────────────────

    public function test_late_renewal_starts_from_now_when_expiry_is_past(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_late_renew',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->subDays(5),
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_late_renew');
        $handler->handle(
            ['id' => 'inv_late_renew', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_late',
            'PAID'
        );

        $subscription->refresh();
        $expectedExpiry = now()->addMonth();
        $this->assertTrue(
            abs($subscription->expires_at->diffInSeconds($expectedExpiry)) < 60
        );
    }

    // ── SETTLED status maps to active / paid ─────────────────────────────────

    public function test_settled_status_activates_subscription(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_settled_direct',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_settled_direct');
        $handler->handle(
            ['id' => 'inv_settled_direct', 'status' => 'SETTLED', 'amount' => 750, 'currency' => 'PHP'],
            'evt_settled',
            'SETTLED'
        );

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PAID,
        ]);
    }

    // ── cancelled status on pending-deletion triggers graceful delete ─────────

    public function test_cancelled_status_on_pending_deletion_triggers_graceful_delete(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create(['deletion_requested_at' => now()]);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_cancel_del',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addDay(),
        ]);

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_cancel_del');
        $handler->handle(
            ['id' => 'inv_cancel_del', 'status' => 'FAILED', 'amount' => 500],
            'evt_cancel_del',
            'FAILED'
        );

        $this->assertSoftDeleted('communities', ['id' => $community->id]);
    }

    // ── catch/rethrow block (lines 200-205) ─────────────────────────────────

    public function test_handle_logs_error_and_rethrows_on_exception(): void
    {
        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_err_rethrow',
            'status' => Subscription::STATUS_PENDING,
            'expires_at' => null,
        ]);

        // Make SyncMembershipFromSubscription throw to trigger the catch block
        $this->mock(SyncMembershipFromSubscription::class)
            ->shouldReceive('execute')
            ->andThrow(new \RuntimeException('sync membership failed'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'HandleSubscriptionPaid failed'
                && $ctx['subscription_id'] === $subscription->id
                && str_contains($ctx['error'], 'sync membership failed'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sync membership failed');

        $handler = app(HandleSubscriptionPaid::class);
        $handler->matches('inv_err_rethrow');
        $handler->handle(
            ['id' => 'inv_err_rethrow', 'status' => 'PAID', 'amount' => 500, 'currency' => 'PHP'],
            'evt_err',
            'PAID'
        );
    }
}
