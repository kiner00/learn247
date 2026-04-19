<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CreatorSubscription;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecurringCancellationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldReceive('deactivateRecurringPlan')->andReturn(['status' => 'INACTIVE']);
        $this->app->instance(XenditService::class, $xendit);
    }

    public function test_cancel_subscription_requires_auth(): void
    {
        $subscription = Subscription::factory()->create(['xendit_plan_id' => 'repl_auth']);

        $this->post("/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertRedirect('/login');
    }

    public function test_cancel_subscription_requires_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $otherUser->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_owned',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertForbidden();
    }

    public function test_cancel_subscription_requires_recurring(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_not_recurring',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertStatus(400);
    }

    public function test_cancel_subscription_succeeds(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_cancel_ok',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/subscriptions/{$subscription->id}/cancel-recurring")
            ->assertRedirect()
            ->assertSessionHas('success');

        $subscription->refresh();
        $this->assertEquals('INACTIVE', $subscription->recurring_status);
    }

    public function test_cancel_creator_plan_succeeds(): void
    {
        $user = User::factory()->create();
        $creatorSub = CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_BASIC,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_creator_cancel_ctrl',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post('/creator/plan/cancel-recurring')
            ->assertRedirect()
            ->assertSessionHas('success');

        $creatorSub->refresh();
        $this->assertEquals('INACTIVE', $creatorSub->recurring_status);
    }

    public function test_cancel_creator_plan_fails_without_active_recurring(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/creator/plan/cancel-recurring')
            ->assertStatus(404);
    }

    // ─── cancelCourseEnrollment ──────────────────────────────────────────────

    public function test_cancel_course_enrollment_succeeds(): void
    {
        $user = User::factory()->create();
        $course = \App\Models\Course::create([
            'community_id' => Community::factory()->create()->id,
            'title' => 'Test Course',
        ]);

        $enrollment = \App\Models\CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => \App\Models\CourseEnrollment::STATUS_PAID,
            'xendit_plan_id' => 'repl_enroll_cancel',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/course-enrollments/{$enrollment->id}/cancel-recurring")
            ->assertRedirect()
            ->assertSessionHas('success');

        $enrollment->refresh();
        $this->assertEquals('INACTIVE', $enrollment->recurring_status);
    }

    public function test_cancel_course_enrollment_requires_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $course = \App\Models\Course::create([
            'community_id' => Community::factory()->create()->id,
            'title' => 'Test Course',
        ]);

        $enrollment = \App\Models\CourseEnrollment::create([
            'user_id' => $otherUser->id,
            'course_id' => $course->id,
            'status' => \App\Models\CourseEnrollment::STATUS_PAID,
            'xendit_plan_id' => 'repl_enroll_other',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/course-enrollments/{$enrollment->id}/cancel-recurring")
            ->assertForbidden();
    }

    public function test_cancel_course_enrollment_requires_recurring(): void
    {
        $user = User::factory()->create();
        $course = \App\Models\Course::create([
            'community_id' => Community::factory()->create()->id,
            'title' => 'Test Course',
        ]);

        $enrollment = \App\Models\CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => \App\Models\CourseEnrollment::STATUS_PAID,
            'xendit_id' => 'inv_not_recurring',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/course-enrollments/{$enrollment->id}/cancel-recurring")
            ->assertStatus(400);
    }

    // ─── cancelCurzzoPurchase ────────────────────────────────────────────────

    public function test_cancel_curzzo_purchase_succeeds(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = \App\Models\Curzzo::create([
            'community_id' => $community->id,
            'name' => 'Test Bot',
            'instructions' => 'Be helpful.',
        ]);

        $purchase = \App\Models\CurzzoPurchase::create([
            'user_id' => $user->id,
            'curzzo_id' => $curzzo->id,
            'status' => \App\Models\CurzzoPurchase::STATUS_PAID,
            'xendit_plan_id' => 'repl_curzzo_cancel',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/curzzo-purchases/{$purchase->id}/cancel-recurring")
            ->assertRedirect()
            ->assertSessionHas('success');

        $purchase->refresh();
        $this->assertEquals('INACTIVE', $purchase->recurring_status);
    }

    public function test_cancel_curzzo_purchase_requires_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = \App\Models\Curzzo::create([
            'community_id' => $community->id,
            'name' => 'Test Bot',
            'instructions' => 'Be helpful.',
        ]);

        $purchase = \App\Models\CurzzoPurchase::create([
            'user_id' => $otherUser->id,
            'curzzo_id' => $curzzo->id,
            'status' => \App\Models\CurzzoPurchase::STATUS_PAID,
            'xendit_plan_id' => 'repl_curzzo_other',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/curzzo-purchases/{$purchase->id}/cancel-recurring")
            ->assertForbidden();
    }

    public function test_cancel_curzzo_purchase_requires_recurring(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = \App\Models\Curzzo::create([
            'community_id' => $community->id,
            'name' => 'Test Bot',
            'instructions' => 'Be helpful.',
        ]);

        $purchase = \App\Models\CurzzoPurchase::create([
            'user_id' => $user->id,
            'curzzo_id' => $curzzo->id,
            'status' => \App\Models\CurzzoPurchase::STATUS_PAID,
            'xendit_id' => 'inv_not_recurring',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->post("/curzzo-purchases/{$purchase->id}/cancel-recurring")
            ->assertStatus(400);
    }

    // ─── enableSubscriptionAutoRenew ─────────────────────────────────────────

    public function test_enable_subscription_auto_renew_succeeds(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $mockEnable = $this->mock(\App\Actions\Billing\EnableAutoRenew::class);
        $mockEnable->shouldReceive('executeForSubscription')
            ->once()
            ->andReturn('https://linking.xendit.co/test-url');

        $response = $this->actingAs($user)
            ->postJson("/subscriptions/{$subscription->id}/enable-auto-renew");

        $response->assertOk();
        $response->assertJsonFragment(['linking_url' => 'https://linking.xendit.co/test-url']);
    }

    public function test_enable_subscription_auto_renew_requires_ownership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $otherUser->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->postJson("/subscriptions/{$subscription->id}/enable-auto-renew")
            ->assertForbidden();
    }

    public function test_enable_subscription_auto_renew_requires_active_status(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subMonth(),
        ]);

        $this->actingAs($user)
            ->postJson("/subscriptions/{$subscription->id}/enable-auto-renew")
            ->assertStatus(400);
    }

    public function test_enable_subscription_auto_renew_rejects_already_recurring(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_already',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->postJson("/subscriptions/{$subscription->id}/enable-auto-renew")
            ->assertStatus(400);
    }

    // ─── enableCreatorPlanAutoRenew ──────────────────────────────────────────

    public function test_enable_creator_plan_auto_renew_succeeds(): void
    {
        $user = User::factory()->create();
        CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_BASIC,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $mockEnable = $this->mock(\App\Actions\Billing\EnableAutoRenew::class);
        $mockEnable->shouldReceive('executeForCreatorPlan')
            ->once()
            ->andReturn('https://linking.xendit.co/creator-test');

        $response = $this->actingAs($user)
            ->postJson('/creator/plan/enable-auto-renew');

        $response->assertOk();
        $response->assertJsonFragment(['linking_url' => 'https://linking.xendit.co/creator-test']);
    }

    public function test_enable_creator_plan_auto_renew_fails_without_active_sub(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/creator/plan/enable-auto-renew')
            ->assertStatus(404);
    }

    public function test_enable_creator_plan_auto_renew_skips_already_recurring(): void
    {
        $user = User::factory()->create();
        CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_BASIC,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_already_creator',
            'expires_at' => now()->addMonth(),
        ]);

        // This sub already has xendit_plan_id so query (whereNull xendit_plan_id) won't find it
        $this->actingAs($user)
            ->postJson('/creator/plan/enable-auto-renew')
            ->assertStatus(404);
    }
}
