<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\WebhookHandlers\RecurringCourseEnrollmentHandler;
use App\Actions\Billing\WebhookHandlers\RecurringCurzzoPurchaseHandler;
use App\Actions\Billing\WebhookHandlers\RecurringSubscriptionHandler;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Curzzo;
use App\Models\CurzzoPurchase;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecurringHandlersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);
    }

    // =====================================================================
    // RecurringSubscriptionHandler
    // =====================================================================

    public function test_subscription_handler_matches_plan_returns_true_for_known_plan(): void
    {
        $handler = app(RecurringSubscriptionHandler::class);

        $community = Community::factory()->create();
        Subscription::create([
            'community_id'   => $community->id,
            'user_id'        => User::factory()->create()->id,
            'status'         => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_sub_match_001',
            'expires_at'     => now()->addMonth(),
        ]);

        $this->assertTrue($handler->matchesPlan('repl_sub_match_001'));
        $this->assertFalse($handler->matchesPlan('repl_nonexistent'));
    }

    public function test_subscription_handler_plan_activated_for_already_active_sub_only_updates_recurring_status(): void
    {
        $handler = app(RecurringSubscriptionHandler::class);

        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $expiresAt = now()->addDays(20);

        $subscription = Subscription::create([
            'community_id'     => $community->id,
            'user_id'          => $user->id,
            'status'           => Subscription::STATUS_ACTIVE,
            'xendit_plan_id'   => 'repl_sub_active_001',
            'recurring_status' => 'REQUIRES_ACTION',
            'expires_at'       => $expiresAt,
        ]);

        $handler->handlePlanActivated(['id' => 'repl_sub_active_001']);

        $subscription->refresh();
        $this->assertEquals('ACTIVE', $subscription->recurring_status);
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
        // expires_at should NOT change for already-active subscription
        $this->assertTrue(
            abs($subscription->expires_at->diffInSeconds($expiresAt)) < 5,
            'Expiry should remain unchanged when subscription is already active'
        );
    }

    public function test_subscription_handler_plan_inactivated_sets_recurring_inactive(): void
    {
        $handler = app(RecurringSubscriptionHandler::class);

        $user = User::factory()->create();
        $community = Community::factory()->create();

        $subscription = Subscription::create([
            'community_id'     => $community->id,
            'user_id'          => $user->id,
            'status'           => Subscription::STATUS_ACTIVE,
            'xendit_plan_id'   => 'repl_sub_inact_001',
            'recurring_status' => 'ACTIVE',
            'expires_at'       => now()->addDays(10),
        ]);

        $handler->handlePlanInactivated(['id' => 'repl_sub_inact_001']);

        $subscription->refresh();
        $this->assertEquals('INACTIVE', $subscription->recurring_status);
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
    }

    public function test_subscription_handler_plan_inactivated_does_nothing_for_unknown_plan(): void
    {
        $handler = app(RecurringSubscriptionHandler::class);

        // Should not throw
        $handler->handlePlanInactivated(['id' => 'repl_unknown_xyz']);

        $this->assertTrue(true); // No exception
    }

    public function test_subscription_handler_cycle_succeeded_no_entity_logs_and_returns(): void
    {
        $handler = app(RecurringSubscriptionHandler::class);

        // Should not throw — just logs and returns
        $handler->handleCycleSucceeded([
            'plan_id'  => 'repl_nonexistent',
            'cycle_id' => 'cycle_nonexistent',
            'amount'   => 100,
        ]);

        $this->assertEquals(0, Payment::count());
    }

    // =====================================================================
    // RecurringCourseEnrollmentHandler
    // =====================================================================

    public function test_course_enrollment_handler_matches_plan(): void
    {
        $handler = app(RecurringCourseEnrollmentHandler::class);

        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Monthly Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        CourseEnrollment::create([
            'user_id'        => User::factory()->create()->id,
            'course_id'      => $course->id,
            'status'         => CourseEnrollment::STATUS_PAID,
            'xendit_plan_id' => 'repl_course_match_001',
            'expires_at'     => now()->addMonth(),
            'paid_at'        => now(),
        ]);

        $this->assertTrue($handler->matchesPlan('repl_course_match_001'));
        $this->assertFalse($handler->matchesPlan('repl_nonexistent'));
    }

    public function test_course_enrollment_handler_plan_activated_for_pending_enrollment(): void
    {
        $handler = app(RecurringCourseEnrollmentHandler::class);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Activated Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'          => $user->id,
            'course_id'        => $course->id,
            'status'           => CourseEnrollment::STATUS_PENDING,
            'xendit_plan_id'   => 'repl_course_activate_001',
            'recurring_status' => 'REQUIRES_ACTION',
        ]);

        $handler->handlePlanActivated(['id' => 'repl_course_activate_001']);

        $enrollment->refresh();
        $this->assertEquals(CourseEnrollment::STATUS_PAID, $enrollment->status);
        $this->assertEquals('ACTIVE', $enrollment->recurring_status);
        $this->assertNotNull($enrollment->expires_at);
        $this->assertTrue($enrollment->expires_at->isFuture());
    }

    public function test_course_enrollment_handler_plan_inactivated(): void
    {
        $handler = app(RecurringCourseEnrollmentHandler::class);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Inactivated Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'          => $user->id,
            'course_id'        => $course->id,
            'status'           => CourseEnrollment::STATUS_PAID,
            'xendit_plan_id'   => 'repl_course_inact_001',
            'recurring_status' => 'ACTIVE',
            'expires_at'       => now()->addDays(15),
            'paid_at'          => now(),
        ]);

        $handler->handlePlanInactivated(['id' => 'repl_course_inact_001']);

        $enrollment->refresh();
        $this->assertEquals('INACTIVE', $enrollment->recurring_status);
        $this->assertEquals(CourseEnrollment::STATUS_PAID, $enrollment->status);
    }

    public function test_course_enrollment_handler_cycle_succeeded_creates_payment(): void
    {
        $handler = app(RecurringCourseEnrollmentHandler::class);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Cycle Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'          => $user->id,
            'course_id'        => $course->id,
            'status'           => CourseEnrollment::STATUS_PAID,
            'xendit_plan_id'   => 'repl_course_cycle_pay',
            'recurring_status' => 'ACTIVE',
            'expires_at'       => now()->addDays(5),
            'paid_at'          => now(),
        ]);

        $handler->handleCycleSucceeded([
            'plan_id'  => 'repl_course_cycle_pay',
            'cycle_id' => 'cycle_course_pay_001',
            'id'       => 'cycle_course_pay_001',
            'amount'   => 200,
            'currency' => 'PHP',
        ]);

        $this->assertDatabaseHas('payments', [
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'amount'          => 200,
            'status'          => Payment::STATUS_PAID,
            'xendit_event_id' => 'cycle_course_pay_001_SUCCEEDED',
        ]);
    }

    // =====================================================================
    // RecurringCurzzoPurchaseHandler
    // =====================================================================

    public function test_curzzo_purchase_handler_matches_plan(): void
    {
        $handler = app(RecurringCurzzoPurchaseHandler::class);

        $community = Community::factory()->create();
        $curzzo = Curzzo::create([
            'community_id' => $community->id,
            'name'         => 'Bot',
            'instructions' => 'Test',
            'billing_type' => 'monthly',
            'price'        => 299,
            'is_active'    => true,
        ]);

        CurzzoPurchase::create([
            'user_id'        => User::factory()->create()->id,
            'curzzo_id'      => $curzzo->id,
            'status'         => CurzzoPurchase::STATUS_PAID,
            'xendit_plan_id' => 'repl_curzzo_match_001',
            'expires_at'     => now()->addMonth(),
            'paid_at'        => now(),
        ]);

        $this->assertTrue($handler->matchesPlan('repl_curzzo_match_001'));
        $this->assertFalse($handler->matchesPlan('repl_nonexistent'));
    }

    public function test_curzzo_purchase_handler_plan_activated_for_pending_purchase(): void
    {
        $handler = app(RecurringCurzzoPurchaseHandler::class);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = Curzzo::create([
            'community_id' => $community->id,
            'name'         => 'Activated Bot',
            'instructions' => 'Test',
            'billing_type' => 'monthly',
            'price'        => 299,
            'is_active'    => true,
        ]);

        $purchase = CurzzoPurchase::create([
            'user_id'          => $user->id,
            'curzzo_id'        => $curzzo->id,
            'status'           => CurzzoPurchase::STATUS_PENDING,
            'xendit_plan_id'   => 'repl_curzzo_activate_001',
            'recurring_status' => 'REQUIRES_ACTION',
        ]);

        $handler->handlePlanActivated(['id' => 'repl_curzzo_activate_001']);

        $purchase->refresh();
        $this->assertEquals(CurzzoPurchase::STATUS_PAID, $purchase->status);
        $this->assertEquals('ACTIVE', $purchase->recurring_status);
        $this->assertNotNull($purchase->expires_at);
        $this->assertTrue($purchase->expires_at->isFuture());
    }

    public function test_curzzo_purchase_handler_plan_inactivated(): void
    {
        $handler = app(RecurringCurzzoPurchaseHandler::class);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = Curzzo::create([
            'community_id' => $community->id,
            'name'         => 'Inactivated Bot',
            'instructions' => 'Test',
            'billing_type' => 'monthly',
            'price'        => 299,
            'is_active'    => true,
        ]);

        $purchase = CurzzoPurchase::create([
            'user_id'          => $user->id,
            'curzzo_id'        => $curzzo->id,
            'status'           => CurzzoPurchase::STATUS_PAID,
            'xendit_plan_id'   => 'repl_curzzo_inact_001',
            'recurring_status' => 'ACTIVE',
            'expires_at'       => now()->addDays(15),
            'paid_at'          => now(),
        ]);

        $handler->handlePlanInactivated(['id' => 'repl_curzzo_inact_001']);

        $purchase->refresh();
        $this->assertEquals('INACTIVE', $purchase->recurring_status);
        $this->assertEquals(CurzzoPurchase::STATUS_PAID, $purchase->status);
    }

    public function test_curzzo_purchase_handler_cycle_succeeded_creates_payment(): void
    {
        $handler = app(RecurringCurzzoPurchaseHandler::class);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = Curzzo::create([
            'community_id' => $community->id,
            'name'         => 'Cycle Bot',
            'instructions' => 'Test',
            'billing_type' => 'monthly',
            'price'        => 299,
            'is_active'    => true,
        ]);

        $purchase = CurzzoPurchase::create([
            'user_id'          => $user->id,
            'curzzo_id'        => $curzzo->id,
            'status'           => CurzzoPurchase::STATUS_PAID,
            'xendit_plan_id'   => 'repl_curzzo_cycle_pay',
            'recurring_status' => 'ACTIVE',
            'expires_at'       => now()->addDays(5),
            'paid_at'          => now(),
        ]);

        $handler->handleCycleSucceeded([
            'plan_id'  => 'repl_curzzo_cycle_pay',
            'cycle_id' => 'cycle_curzzo_pay_001',
            'id'       => 'cycle_curzzo_pay_001',
            'amount'   => 299,
            'currency' => 'PHP',
        ]);

        $this->assertDatabaseHas('payments', [
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'amount'          => 299,
            'status'          => Payment::STATUS_PAID,
            'xendit_event_id' => 'cycle_curzzo_pay_001_SUCCEEDED',
        ]);
    }

    public function test_curzzo_purchase_handler_cycle_succeeded_extends_expiry(): void
    {
        $handler = app(RecurringCurzzoPurchaseHandler::class);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = Curzzo::create([
            'community_id' => $community->id,
            'name'         => 'Expiry Bot',
            'instructions' => 'Test',
            'billing_type' => 'monthly',
            'price'        => 299,
            'is_active'    => true,
        ]);

        $originalExpiry = now()->addDays(5);
        $purchase = CurzzoPurchase::create([
            'user_id'          => $user->id,
            'curzzo_id'        => $curzzo->id,
            'status'           => CurzzoPurchase::STATUS_PAID,
            'xendit_plan_id'   => 'repl_curzzo_expiry_001',
            'recurring_status' => 'ACTIVE',
            'expires_at'       => $originalExpiry,
            'paid_at'          => now(),
        ]);

        $handler->handleCycleSucceeded([
            'plan_id'  => 'repl_curzzo_expiry_001',
            'cycle_id' => 'cycle_curzzo_exp_001',
            'id'       => 'cycle_curzzo_exp_001',
            'amount'   => 299,
            'currency' => 'PHP',
        ]);

        $purchase->refresh();
        $expectedExpiry = $originalExpiry->copy()->addMonth();
        $this->assertTrue(
            abs($purchase->expires_at->diffInSeconds($expectedExpiry)) < 5,
            'Expiry should be extended by 1 month from original'
        );
    }

    // ─── Idempotency across handlers ─────────────────────────────────────────

    public function test_course_enrollment_cycle_succeeded_idempotency(): void
    {
        $handler = app(RecurringCourseEnrollmentHandler::class);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Idempotent Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'          => $user->id,
            'course_id'        => $course->id,
            'status'           => CourseEnrollment::STATUS_PAID,
            'xendit_plan_id'   => 'repl_course_idemp',
            'recurring_status' => 'ACTIVE',
            'expires_at'       => now()->addDays(5),
            'paid_at'          => now(),
        ]);

        // Pre-create payment (simulating already processed)
        Payment::create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'amount'          => 200,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'xendit_event_id' => 'cycle_course_idemp_001_SUCCEEDED',
            'metadata'        => [],
            'paid_at'         => now(),
        ]);

        $handler->handleCycleSucceeded([
            'plan_id'  => 'repl_course_idemp',
            'cycle_id' => 'cycle_course_idemp_001',
            'id'       => 'cycle_course_idemp_001',
            'amount'   => 200,
        ]);

        $this->assertEquals(1, Payment::where('xendit_event_id', 'cycle_course_idemp_001_SUCCEEDED')->count());
    }
}
