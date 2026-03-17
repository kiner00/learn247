<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\HandleXenditWebhook;
use App\Mail\TempPasswordMail;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HandleXenditWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $body, ?string $callbackToken = null): Request
    {
        $request = Request::create('/xendit/webhook', 'POST', $body);
        if ($callbackToken) {
            $request->headers->set('x-callback-token', $callbackToken);
        }
        return $request;
    }

    public function test_rejects_invalid_callback_token(): void
    {
        config(['services.xendit.callback_token' => 'valid-token']);

        $request = $this->makeRequest(['id' => 'inv_123', 'status' => 'PAID'], 'wrong-token');
        $action = app(HandleXenditWebhook::class);

        $this->expectException(HttpException::class);
        $action->execute($request);
    }

    public function test_processes_paid_invoice_creates_payment_and_membership(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_paid_123',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'       => 'inv_paid_123',
            'status'   => 'PAID',
            'amount'   => 500,
            'currency' => 'PHP',
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status'          => Payment::STATUS_PAID,
            'amount'          => 500,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
    }

    public function test_idempotency_skips_duplicate_events(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_dup_123',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        Payment::create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'amount'          => 500,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'xendit_event_id' => 'inv_dup_123_PAID',
            'metadata'        => [],
            'paid_at'         => now(),
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_dup_123',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertEquals(1, Payment::where('xendit_event_id', 'inv_dup_123_PAID')->count());
    }

    public function test_skips_non_invoice_events(): void
    {
        config(['services.xendit.callback_token' => 'valid-token']);

        $request = $this->makeRequest([
            'event' => 'disbursement.completed',
            'data'  => ['id' => 'dis_123', 'status' => 'COMPLETED'],
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertEquals(0, Payment::count());
    }

    public function test_no_matching_subscription_is_skipped(): void
    {
        config(['services.xendit.callback_token' => 'valid-token']);

        $request = $this->makeRequest([
            'id'     => 'inv_nonexistent',
            'status' => 'PAID',
            'amount' => 100,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertEquals(0, Payment::count());
    }

    public function test_auto_creates_affiliate_for_paid_subscriber(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_aff_auto',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_aff_auto',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('affiliates', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);
    }

    public function test_failed_status_cancels_subscription(): void
    {
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_fail_123',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_fail_123',
            'status' => 'FAILED',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => Subscription::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status'          => Payment::STATUS_FAILED,
        ]);
    }

    public function test_guest_user_receives_temp_password_email(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => true]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_guest_123',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_guest_123',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        Mail::assertQueued(TempPasswordMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_settled_status_activates_subscription_and_creates_paid_payment(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_settled_1',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'       => 'inv_settled_1',
            'status'   => 'SETTLED',
            'amount'   => 750,
            'currency' => 'PHP',
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status'          => Payment::STATUS_PAID,
            'amount'          => 750,
        ]);
    }

    public function test_expired_status_expires_subscription(): void
    {
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_exp_1',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_exp_1',
            'status' => 'EXPIRED',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => Subscription::STATUS_EXPIRED,
        ]);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status'          => Payment::STATUS_EXPIRED,
        ]);
    }

    public function test_unknown_status_keeps_pending_and_skips_payment_creation(): void
    {
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_unk_1',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_unk_1',
            'status' => 'PENDING',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => Subscription::STATUS_PENDING,
        ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_v2_event_format_extracts_payload_from_data_field(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_v2_1',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'event' => 'invoice.paid',
            'data'  => [
                'id'       => 'inv_v2_1',
                'status'   => 'PAID',
                'amount'   => 600,
                'currency' => 'PHP',
            ],
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status'          => Payment::STATUS_PAID,
            'amount'          => 600,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_early_renewal_extends_from_existing_future_expiry(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $futureExpiry = now()->addDays(15);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_renew_1',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => $futureExpiry,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_renew_1',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $subscription->refresh();
        $this->assertTrue(
            $subscription->expires_at->greaterThan($futureExpiry),
            'Early renewal should extend from the existing future expiry date'
        );
        $expectedExpiry = $futureExpiry->copy()->addMonth();
        $this->assertTrue(
            $subscription->expires_at->diffInSeconds($expectedExpiry) < 5,
            'Expiry should be roughly one month after the original future expiry'
        );
    }

    public function test_records_affiliate_conversion_when_subscription_has_affiliate(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'                  => $owner->id,
            'affiliate_commission_rate' => 10,
        ]);

        $affiliateUser = User::factory()->create(['needs_password_setup' => false]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
            'code'         => 'AFF_CONV_TEST',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
            'xendit_id'    => 'inv_aff_sub',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        $referredUser = User::factory()->create(['needs_password_setup' => false]);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $referredUser->id,
            'affiliate_id' => $affiliate->id,
            'xendit_id'    => 'inv_aff_conv_1',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_aff_conv_1',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id'     => $affiliate->id,
            'subscription_id'  => $subscription->id,
            'referred_user_id' => $referredUser->id,
        ]);
    }

    public function test_mail_failure_does_not_rollback_payment(): void
    {
        Mail::fake();
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP down'));

        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => true]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_mail_fail',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_mail_fail',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status'          => Payment::STATUS_PAID,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_skips_affiliate_creation_when_already_exists(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();

        Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'         => 'EXISTING_CODE',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_aff_dup',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_aff_dup',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertEquals(
            1,
            Affiliate::where('community_id', $community->id)->where('user_id', $user->id)->count(),
            'Should not create a duplicate affiliate'
        );
    }

    // ─── course enrollment webhook ──────────────────────────────────────────────

    public function test_paid_invoice_for_course_enrollment_marks_enrollment_paid(): void
    {
        config(['services.xendit.callback_token' => 'valid-token']);

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_course_paid_1',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_course_paid_1',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('course_enrollments', [
            'id'     => $enrollment->id,
            'status' => CourseEnrollment::STATUS_PAID,
        ]);

        $enrollment->refresh();
        $this->assertNotNull($enrollment->paid_at);
        $this->assertNull($enrollment->expires_at); // paid_once has no expiry
    }

    public function test_paid_invoice_for_monthly_course_sets_expires_at(): void
    {
        config(['services.xendit.callback_token' => 'valid-token']);

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Monthly Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_course_monthly_1',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_course_monthly_1',
            'status' => 'PAID',
            'amount' => 200,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $enrollment->refresh();
        $this->assertEquals(CourseEnrollment::STATUS_PAID, $enrollment->status);
        $this->assertNotNull($enrollment->expires_at);
        $this->assertTrue($enrollment->expires_at->isFuture());
    }

    public function test_non_paid_status_for_course_enrollment_does_not_mark_paid(): void
    {
        config(['services.xendit.callback_token' => 'valid-token']);

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_course_failed_1',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_course_failed_1',
            'status' => 'FAILED',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        // Status should remain pending (not paid)
        $this->assertDatabaseHas('course_enrollments', [
            'id'     => $enrollment->id,
            'status' => CourseEnrollment::STATUS_PENDING,
        ]);

        // No subscription payment record created for course enrollments
        $this->assertEquals(0, Payment::count());
    }

    public function test_course_enrollment_webhook_does_not_process_as_subscription(): void
    {
        config(['services.xendit.callback_token' => 'valid-token']);

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_course_no_sub',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_course_no_sub',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        // No payment record should be created (course enrollments skip subscription logic)
        $this->assertEquals(0, Payment::count());
    }
}
