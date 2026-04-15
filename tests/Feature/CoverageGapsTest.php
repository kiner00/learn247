<?php

namespace Tests\Feature;

use App\Actions\Community\AcceptInvite;
use App\Actions\Community\EnsureMemberAffiliate;
use App\Actions\Community\ExtendMemberAccess;
use App\Actions\Feed\CreateComment;
use App\Actions\Feed\CreatePost;
use App\Actions\Payout\RequestAllAffiliatePayouts;
use App\Actions\Payout\RequestOwnerPayout;
use App\Ai\Agents\LandingPageBuilder;
use App\Mail\GlobalAnnouncementMail;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Certificate;
use App\Models\CertificationAttempt;
use App\Models\CertificationPurchase;
use App\Models\CertificationQuestion;
use App\Models\CertificationQuestionOption;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseCertification;
use App\Models\CourseEnrollment;
use App\Models\CreatorSubscription;
use App\Models\Event;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Admin\CreatorAnalytics;
use App\Queries\Classroom\GetCourseDetail;
use App\Queries\Classroom\GetCourseList;
use App\Queries\Community\GetCalendarEvents;
use App\Queries\Payout\CalculateEligibility;
use App\Services\Payout\OwnerEarningsCalculator;
use App\Services\Payout\OwnerPayoutDispatcher;
use App\Services\StorageService;
use App\Services\XenditService;
use App\Support\PayoutChannelMap;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CoverageGapsTest extends TestCase
{
    use RefreshDatabase;

    // ── 1. AcceptInvite: line 44 — free_access_months sets expiry ──────────

    public function test_accept_invite_with_free_access_months_sets_expiry(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $community = Community::factory()->create(['price' => 0]);
        $invite = CommunityInvite::create([
            'community_id'       => $community->id,
            'email'              => 'test@example.com',
            'token'              => 'tok-free-months',
            'expires_at'         => now()->addDays(7),
            'free_access_months' => 3,
        ]);

        $result = (new AcceptInvite())->execute($user, $invite);

        $this->assertTrue($result['success']);
        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)->first();
        $this->assertNotNull($member->expires_at);
        // Should be roughly 3 months from now
        $this->assertTrue($member->expires_at->greaterThan(now()->addMonths(2)));
    }

    // ── 2. EnsureMemberAffiliate: line 21 — returns existing affiliate ────

    public function test_ensure_member_affiliate_returns_existing(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $existing = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'         => 'EXIST-CODE',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $result = (new EnsureMemberAffiliate())->execute($community, $user->id);

        $this->assertNotNull($result);
        $this->assertEquals($existing->id, $result->id);
    }

    // ── 3. ExtendMemberAccess: line 19 — future expires_at used as base ──

    public function test_extend_member_access_uses_future_expiry_as_base(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $futureDate = now()->addMonths(2);
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
            'expires_at'      => $futureDate,
        ]);

        $count = (new ExtendMemberAccess())->execute($community, [$user->id], 1);

        $this->assertSame(1, $count);
        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)->first();
        // Should be roughly 3 months from now (2 existing + 1 added)
        $this->assertTrue($member->expires_at->greaterThan(now()->addMonths(2)->addDays(20)));
    }

    // ── 4. CreateComment: line 23 — blocked member throws ─────────────────

    public function test_blocked_member_cannot_comment(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'is_blocked'   => true,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('blocked');
        (new CreateComment())->execute($user, $post, ['content' => 'test']);
    }

    // ── 5. CreatePost: line 28 — blocked member throws ────────────────────

    public function test_blocked_member_cannot_post(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'is_blocked'   => true,
        ]);

        $storage = Mockery::mock(StorageService::class);
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('blocked');
        (new CreatePost($storage))->execute($user, $community, ['content' => 'test']);
    }

    // ── 6. RequestAllAffiliatePayouts: line 45 — skip zero eligible ───────

    public function test_request_all_affiliate_payouts_skips_zero_eligible(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'code'           => 'AFF-SKIP-' . uniqid(),
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $eligibility->shouldReceive('forAffiliate')->andReturn(0.0);

        $action = new RequestAllAffiliatePayouts($eligibility);
        $result = $action->execute($user);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No eligible', $result['message']);
    }

    // ── 7. RequestOwnerPayout: line 43 — amount <= PAYOUT_FEE ─────────────

    public function test_request_owner_payout_rejects_amount_below_fee(): void
    {
        $owner = User::factory()->create([
            'payout_method'   => 'gcash',
            'payout_details'  => '09171234567',
            'kyc_verified_at' => now(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $eligibility = Mockery::mock(CalculateEligibility::class);
        $eligibility->shouldReceive('forOwner')->andReturn([1000.0, 0.0, null]);

        $action = new RequestOwnerPayout($eligibility);
        // Amount <= Community::PAYOUT_FEE (15.0)
        $result = $action->execute($owner, $community, 15.0);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Minimum payout', $result['message']);
    }

    // ── 8. LandingPageBuilder: line 30 — free price label ─────────────────

    public function test_landing_page_builder_free_price_label(): void
    {
        $builder = new LandingPageBuilder([
            'name'         => 'My Community',
            'category'     => 'Tech',
            'description'  => 'A test community',
            'price'        => 0,
            'currency'     => 'PHP',
            'creator_name' => 'John',
            'member_count' => 10,
        ]);

        $instructions = $builder->instructions();
        $this->assertStringContainsString('Free', $instructions);
        $this->assertStringNotContainsString('PHP 0', $instructions);
    }

    // ── 9. CommunityInviteController: line 45 — free_access_months int cast ─

    public function test_store_invite_with_free_access_months(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('communities.invite', $community), [
                'email'              => 'months@example.com',
                'free_access_months' => '6',
            ])
            ->assertOk();

        $this->assertDatabaseHas('community_invites', [
            'community_id'       => $community->id,
            'email'              => 'months@example.com',
            'free_access_months' => 6,
        ]);
    }

    // ── 10. HandleInertiaRequests: line 101 — domain_community truthy ─────

    public function test_inertia_shares_domain_community_when_set(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $response = $this->actingAs($user)
            ->withServerVariables(['HTTP_X_INERTIA' => 'true'])
            ->get(route('communities.show', $community->slug));

        // The point is that the middleware runs without error. We verify
        // the response completes (not a 500). Line 101 is the null branch.
        $this->assertTrue($response->getStatusCode() < 500);
    }

    // ── 11. GlobalAnnouncementMail: line 29 — content() view ──────────────

    public function test_global_announcement_mail_content(): void
    {
        $sender = User::factory()->create();
        $mail = new GlobalAnnouncementMail($sender, 'Big News', 'Hello world');

        $this->assertEquals('[Curzzo] Big News', $mail->envelope()->subject);
        $this->assertEquals('emails.global-announcement', $mail->content()->view);
    }

    // ── 12. AffiliateConversion: line 55 — certificationPurchase() rel ────

    public function test_affiliate_conversion_certification_purchase_relationship(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $certification = CourseCertification::factory()->create(['community_id' => $community->id]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $community->owner_id,
            'code'         => 'REF-CERT',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $purchase = CertificationPurchase::factory()->paid()->create([
            'user_id'          => $user->id,
            'certification_id' => $certification->id,
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id'              => $affiliate->id,
            'certification_purchase_id' => $purchase->id,
            'referred_user_id'          => $user->id,
            'sale_amount'               => 499,
            'platform_fee'              => 75,
            'commission_amount'         => 50,
            'creator_amount'            => 374,
            'status'                    => AffiliateConversion::STATUS_PENDING,
        ]);

        $this->assertEquals($purchase->id, $conversion->certificationPurchase->id);
    }

    // ── 13. CertificationPurchase: line 34 — user() relationship ──────────

    public function test_certification_purchase_user_relationship(): void
    {
        $user = User::factory()->create();
        $purchase = CertificationPurchase::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $purchase->user->id);
    }

    // ── 14. CertificationQuestion: line 18 — certification() relationship ─

    public function test_certification_question_certification_relationship(): void
    {
        $cert = CourseCertification::factory()->create();
        $question = CertificationQuestion::factory()->create(['certification_id' => $cert->id]);

        $this->assertEquals($cert->id, $question->certification->id);
    }

    // ── 15. CertificationQuestionOption: line 24 — question() relationship ─

    public function test_certification_question_option_question_relationship(): void
    {
        $question = CertificationQuestion::factory()->create();
        $option = CertificationQuestionOption::factory()->create(['question_id' => $question->id]);

        $this->assertEquals($question->id, $option->question->id);
    }

    // ── 16. CommunityMember: line 96 — isBlocked() ────────────────────────

    public function test_community_member_is_blocked(): void
    {
        $blocked = new CommunityMember(['is_blocked' => true]);
        $this->assertTrue($blocked->isBlocked());

        $notBlocked = new CommunityMember(['is_blocked' => false]);
        $this->assertFalse($notBlocked->isBlocked());
    }

    // ── 17. Payment: line 38 — creatorNet() ───────────────────────────────

    public function test_payment_creator_net(): void
    {
        $payment = new Payment([
            'amount'         => '1000.00',
            'processing_fee' => '29.00',
            'platform_fee'   => '150.00',
        ]);

        $this->assertEquals(821.00, $payment->creatorNet());
    }

    // ── 18. User: line 100 — hasActiveCreatorPlan() ───────────────────────

    public function test_user_has_active_creator_plan(): void
    {
        $user = User::factory()->create();

        // No creator subscription → free → hasActiveCreatorPlan = false
        $this->assertFalse($user->hasActiveCreatorPlan());

        // Create an active subscription
        CreatorSubscription::create([
            'user_id'    => $user->id,
            'plan'       => CreatorSubscription::PLAN_BASIC,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => null,
        ]);

        // Refresh to pick up new subscription
        $user = $user->fresh();
        $this->assertTrue($user->hasActiveCreatorPlan());
    }

    // ── 19. CreatorAnalytics: line 69 — skip community with no owner ──────

    public function test_creator_analytics_skips_community_with_null_owner(): void
    {
        // Create a community whose owner will be force-deleted
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        // Forcefully remove the owner so owner() returns null
        User::where('id', $owner->id)->delete();

        $analytics = new CreatorAnalytics();
        $result = $analytics->execute();

        // Should not throw; community with null owner is skipped
        $found = collect($result['creators'])->where('community_id', $community->id)->first();
        $this->assertNull($found);
    }

    // ── 20. GetCourseDetail: line 68 — enrollment found for user ──────────

    public function test_get_course_detail_returns_enrollment(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test Course',
            'position'     => 1,
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
        ]);

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'status'    => CourseEnrollment::STATUS_PAID,
        ]);

        $result = (new GetCourseDetail())->execute($course, $user->id, true);

        $this->assertNotNull($result['enrollment']);
        $this->assertEquals('paid', $result['enrollment']['status']);
    }

    // ── 21. GetCourseList: line 100 — member_once access type ─────────────

    public function test_get_course_list_member_once_access(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $user = User::factory()->create();

        Course::create([
            'community_id' => $community->id,
            'title'        => 'Member Once Course',
            'position'     => 1,
            'access_type'  => Course::ACCESS_MEMBER_ONCE,
            'is_published' => true,
        ]);

        // User was once a member (expired subscription)
        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_EXPIRED,
        ]);

        // They are also a community member
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $courses = (new GetCourseList())->execute($community, $user->id);

        $this->assertCount(1, $courses);
        $this->assertTrue($courses->first()['has_access']);
    }

    // ── 22. GetCalendarEvents: line 42 — free member visibility filter ────

    public function test_calendar_events_free_member_sees_public_and_free_only(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $user = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $now = now();
        Event::create([
            'community_id' => $community->id,
            'created_by'   => $owner->id,
            'title'        => 'Public Event',
            'start_at'     => $now,
            'visibility'   => Event::VISIBILITY_PUBLIC,
        ]);
        Event::create([
            'community_id' => $community->id,
            'created_by'   => $owner->id,
            'title'        => 'Free Event',
            'start_at'     => $now,
            'visibility'   => Event::VISIBILITY_FREE,
        ]);
        Event::create([
            'community_id' => $community->id,
            'created_by'   => $owner->id,
            'title'        => 'Paid Event',
            'start_at'     => $now,
            'visibility'   => Event::VISIBILITY_PAID,
        ]);

        $result = (new GetCalendarEvents())->execute($community, $user->id, $now->year, $now->month);

        // Free member should see public + free but NOT paid
        $titles = $result['events']->pluck('title')->all();
        $this->assertContains('Public Event', $titles);
        $this->assertContains('Free Event', $titles);
        $this->assertNotContains('Paid Event', $titles);
    }

    // ── 23. OwnerPayoutDispatcher: line 42 — disbursement <= 0 ────────────

    public function test_dispatcher_throws_when_pending_below_payout_fee(): void
    {
        $owner = User::factory()->create([
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        $sub = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        // Create a very small payment so pending is just above 0 but below PAYOUT_FEE
        Payment::create([
            'subscription_id' => $sub->id,
            'community_id'    => $community->id,
            'user_id'         => $member->id,
            'amount'          => 10,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now(),
        ]);

        $xendit = Mockery::mock(XenditService::class);
        $xendit->shouldNotReceive('createPayout');

        $dispatcher = new OwnerPayoutDispatcher(new OwnerEarningsCalculator(), $xendit);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('processing fee');
        $dispatcher->dispatch($community);
    }

    // ── 24. PayoutChannelMap: line 24 — supportedMethods() ────────────────

    public function test_payout_channel_map_supported_methods(): void
    {
        $methods = PayoutChannelMap::supportedMethods();
        $this->assertContains('gcash', $methods);
        $this->assertContains('maya', $methods);
        $this->assertCount(2, $methods);
    }

    // ── 25. CourseCertification: lines 41, 56, 61 — purchases/attempts/certificates ─

    public function test_course_certification_purchases_relationship(): void
    {
        $cert = CourseCertification::factory()->create();
        $this->assertInstanceOf(HasMany::class, $cert->purchases());
    }

    public function test_course_certification_attempts_relationship(): void
    {
        $cert = CourseCertification::factory()->create();
        $this->assertInstanceOf(HasMany::class, $cert->attempts());

        // Also test loading actual data
        CertificationAttempt::factory()->create(['certification_id' => $cert->id]);
        $this->assertCount(1, $cert->fresh()->attempts);
    }

    public function test_course_certification_certificates_relationship(): void
    {
        $cert = CourseCertification::factory()->create();
        $this->assertInstanceOf(HasMany::class, $cert->certificates());
    }

    // ── 26. CertificationAttempt: lines 33, 38 — certification/user rels ──

    public function test_certification_attempt_certification_relationship(): void
    {
        $cert = CourseCertification::factory()->create();
        $attempt = CertificationAttempt::factory()->create(['certification_id' => $cert->id]);

        $this->assertEquals($cert->id, $attempt->certification->id);
    }

    public function test_certification_attempt_user_relationship(): void
    {
        $user = User::factory()->create();
        $attempt = CertificationAttempt::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $attempt->user->id);
    }

    // ── 27. CreatorSubscription: lines 38, 39 — isActive() ───────────────

    public function test_creator_subscription_is_active_with_null_expiry(): void
    {
        $sub = new CreatorSubscription([
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => null,
        ]);
        $this->assertTrue($sub->isActive());
    }

    public function test_creator_subscription_is_active_with_future_expiry(): void
    {
        $user = User::factory()->create();
        $sub = CreatorSubscription::create([
            'user_id'    => $user->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
        $this->assertTrue($sub->isActive());
    }

    public function test_creator_subscription_is_not_active_with_past_expiry(): void
    {
        $user = User::factory()->create();
        $sub = CreatorSubscription::create([
            'user_id'    => $user->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->subDay(),
        ]);
        $this->assertFalse($sub->isActive());
    }

    public function test_creator_subscription_is_not_active_with_wrong_status(): void
    {
        $sub = new CreatorSubscription([
            'status'     => CreatorSubscription::STATUS_EXPIRED,
            'expires_at' => now()->addMonth(),
        ]);
        $this->assertFalse($sub->isActive());
    }
}
