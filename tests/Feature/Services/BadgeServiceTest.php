<?php

namespace Tests\Feature\Services;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseCertification;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\LessonCompletion;
use App\Models\OwnerPayout;
use App\Models\Post;
use App\Models\QuizAttempt;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private BadgeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BadgeService::class);
    }

    private function createBadge(array $attrs): Badge
    {
        return Badge::create(array_merge([
            'icon' => '🏅',
            'description' => 'Test badge',
        ], $attrs));
    }

    // ─── seedDefaults ─────────────────────────────────────────────────────────

    public function test_seed_defaults_creates_all_platform_badges(): void
    {
        BadgeService::seedDefaults();

        $this->assertDatabaseCount('badges', 21);
        $this->assertDatabaseHas('badges', ['key' => 'early_bird']);
        $this->assertDatabaseHas('badges', ['key' => 'early_builder']);
        $this->assertDatabaseHas('badges', ['key' => 'pioneer_member']);
        $this->assertDatabaseHas('badges', ['key' => 'course_crusader']);
        $this->assertDatabaseHas('badges', ['key' => 'pioneer_creator']);
    }

    public function test_seed_defaults_is_idempotent(): void
    {
        BadgeService::seedDefaults();
        BadgeService::seedDefaults();

        $this->assertDatabaseCount('badges', 21);
    }

    // ─── evaluate: lessons_completed ──────────────────────────────────────────

    public function test_evaluate_awards_lessons_completed_badge(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1']);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson = CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 1]);

        $this->createBadge([
            'key' => 'first_lesson',
            'type' => 'member',
            'name' => 'First Lesson',
            'condition_type' => 'lessons_completed',
            'condition_value' => 1,
            'community_id' => $community->id,
            'sort_order' => 1,
        ]);

        LessonCompletion::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        $this->service->evaluate($user, $community->id);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'community_id' => $community->id,
        ]);
    }

    public function test_evaluate_does_not_award_lessons_completed_when_threshold_not_met(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $this->createBadge([
            'key' => 'five_lessons',
            'type' => 'member',
            'name' => 'Five Lessons',
            'condition_type' => 'lessons_completed',
            'condition_value' => 5,
            'community_id' => $community->id,
            'sort_order' => 1,
        ]);

        $this->service->evaluate($user, $community->id);

        $this->assertDatabaseMissing('user_badges', ['user_id' => $user->id]);
    }

    // ─── evaluate: posts_created ──────────────────────────────────────────────

    public function test_evaluate_awards_posts_created_badge(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $this->createBadge([
            'key' => 'first_post',
            'type' => 'member',
            'name' => 'First Post',
            'condition_type' => 'posts_created',
            'condition_value' => 1,
            'community_id' => $community->id,
            'sort_order' => 1,
        ]);

        Post::factory()->create(['user_id' => $user->id, 'community_id' => $community->id]);

        $this->service->evaluate($user, $community->id);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'community_id' => $community->id,
        ]);
    }

    // ─── evaluate: level_reached ──────────────────────────────────────────────

    public function test_evaluate_awards_level_reached_badge(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'points' => 500,
        ]);

        $this->createBadge([
            'key' => 'level_2',
            'type' => 'member',
            'name' => 'Level 2',
            'condition_type' => 'level_reached',
            'condition_value' => 2,
            'community_id' => $community->id,
            'sort_order' => 1,
        ]);

        $this->service->evaluate($user, $community->id);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'community_id' => $community->id,
        ]);
    }

    public function test_level_reached_returns_1_without_community(): void
    {
        $user = User::factory()->create();

        $this->createBadge([
            'key' => 'level_5',
            'type' => 'member',
            'name' => 'Level 5',
            'condition_type' => 'level_reached',
            'condition_value' => 5,
            'community_id' => null,
            'sort_order' => 1,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseMissing('user_badges', ['user_id' => $user->id]);
    }

    // ─── evaluate: quiz_passed ────────────────────────────────────────────────

    public function test_evaluate_awards_quiz_passed_badge(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1']);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson = CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 1]);
        $quiz = \App\Models\Quiz::create([
            'lesson_id' => $lesson->id,
            'title' => 'Q1',
            'pass_score' => 70,
        ]);

        QuizAttempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'score' => 100,
            'passed' => true,
            'answers' => json_encode([]),
        ]);

        $this->createBadge([
            'key' => 'quiz_master',
            'type' => 'member',
            'name' => 'Quiz Master',
            'condition_type' => 'quiz_passed',
            'condition_value' => 1,
            'community_id' => $community->id,
            'sort_order' => 1,
        ]);

        $this->service->evaluate($user, $community->id);

        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id]);
    }

    // ─── evaluate: pioneer_member ─────────────────────────────────────────────

    public function test_evaluate_awards_pioneer_member_badge(): void
    {
        $user = User::factory()->create();

        $this->createBadge([
            'key' => 'pioneer_member',
            'type' => 'member',
            'name' => 'Pioneer Member',
            'condition_type' => 'pioneer_member',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 10,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'community_id' => null,
        ]);
    }

    // ─── evaluate: early_bird ─────────────────────────────────────────────────

    public function test_evaluate_awards_early_bird_when_has_paid_referral(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'code' => 'ABC123',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => Subscription::factory()->create(['community_id' => $community->id])->id,
            'sale_amount' => 499,
            'platform_fee' => 74.85,
            'commission_amount' => 42.42,
            'creator_amount' => 381.73,
            'referred_user_id' => User::factory()->create()->id,
            'status' => AffiliateConversion::STATUS_PAID,
        ]);

        $this->createBadge([
            'key' => 'early_bird',
            'type' => 'member',
            'name' => 'Early Bird',
            'condition_type' => 'early_bird',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 5,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id]);
        $this->assertDatabaseHas('crz_token_transactions', [
            'user_id' => $user->id,
            'type' => 'award',
            'reason' => 'early_bird_badge',
        ]);
    }

    public function test_early_bird_not_awarded_without_referral(): void
    {
        $user = User::factory()->create();

        $this->createBadge([
            'key' => 'early_bird',
            'type' => 'member',
            'name' => 'Early Bird',
            'condition_type' => 'early_bird',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 5,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseMissing('user_badges', ['user_id' => $user->id]);
    }

    // ─── evaluate: early_builder ──────────────────────────────────────────────

    public function test_evaluate_awards_early_builder_with_10_paying_members(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        for ($i = 0; $i < 10; $i++) {
            Subscription::factory()->create([
                'community_id' => $community->id,
                'status' => 'active',
            ]);
        }

        $this->createBadge([
            'key' => 'early_builder',
            'type' => 'creator',
            'name' => 'Early Builder',
            'condition_type' => 'early_builder',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 195,
        ]);

        $this->service->evaluate($owner);

        $this->assertDatabaseHas('user_badges', ['user_id' => $owner->id]);
        $this->assertDatabaseHas('crz_token_transactions', [
            'user_id' => $owner->id,
            'reason' => 'early_builder_badge',
        ]);
    }

    public function test_early_builder_not_awarded_with_few_subs(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        Subscription::factory()->create([
            'community_id' => $community->id,
            'status' => 'active',
        ]);

        $this->createBadge([
            'key' => 'early_builder',
            'type' => 'creator',
            'name' => 'Early Builder',
            'condition_type' => 'early_builder',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 195,
        ]);

        $this->service->evaluate($owner);

        $this->assertDatabaseMissing('user_badges', ['user_id' => $owner->id]);
    }

    // ─── evaluate: affiliate_referrals ────────────────────────────────────────

    public function test_evaluate_awards_affiliate_referrals_badge(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'code' => 'REF001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        for ($i = 0; $i < 5; $i++) {
            AffiliateConversion::create([
                'affiliate_id' => $affiliate->id,
                'subscription_id' => Subscription::factory()->create(['community_id' => $community->id])->id,
                'referred_user_id' => User::factory()->create()->id,
                'sale_amount' => 499,
                'platform_fee' => 74.85,
                'commission_amount' => 42.42,
                'creator_amount' => 381.73,
                'status' => AffiliateConversion::STATUS_PAID,
            ]);
        }

        $this->createBadge([
            'key' => 'affiliate',
            'type' => 'member',
            'name' => 'Affiliate',
            'condition_type' => 'affiliate_referrals',
            'condition_value' => 5,
            'community_id' => null,
            'sort_order' => 80,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id]);
    }

    // ─── evaluate: affiliate_commission ───────────────────────────────────────

    public function test_evaluate_awards_affiliate_commission_badge(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'code' => 'COM001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => Subscription::factory()->create(['community_id' => $community->id])->id,
            'referred_user_id' => User::factory()->create()->id,
            'sale_amount' => 50000,
            'platform_fee' => 7500,
            'commission_amount' => 10000,
            'creator_amount' => 32500,
            'status' => AffiliateConversion::STATUS_PAID,
        ]);

        $this->createBadge([
            'key' => 'affiliate_10k',
            'type' => 'member',
            'name' => 'Affiliate 10k',
            'condition_type' => 'affiliate_commission',
            'condition_value' => 10000,
            'community_id' => null,
            'sort_order' => 90,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id]);
    }

    // ─── evaluate: course_crusader ────────────────────────────────────────────

    public function test_evaluate_awards_course_crusader_badge(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1']);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson1 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 1]);
        $lesson2 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L2', 'position' => 2]);

        $start = now();
        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson1->id, 'created_at' => $start]);
        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson2->id, 'created_at' => $start->copy()->addDays(5)]);

        $this->createBadge([
            'key' => 'course_crusader',
            'type' => 'member',
            'name' => 'Course Crusader',
            'condition_type' => 'course_crusader',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 60,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id]);
    }

    public function test_course_crusader_not_awarded_if_incomplete_course(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1']);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson1 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 1]);
        CourseLesson::create(['module_id' => $module->id, 'title' => 'L2', 'position' => 2]);

        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson1->id]);

        $this->createBadge([
            'key' => 'course_crusader',
            'type' => 'member',
            'name' => 'Course Crusader',
            'condition_type' => 'course_crusader',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 60,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseMissing('user_badges', ['user_id' => $user->id]);
    }

    // ─── evaluate: certified_completions ──────────────────────────────────────

    public function test_evaluate_awards_certified_completions_badge(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $certification = CourseCertification::create([
            'community_id' => $community->id,
            'title' => 'Test Cert',
            'cert_title' => 'Test Certificate',
            'pass_score' => 70,
        ]);

        Certificate::create([
            'user_id' => User::factory()->create()->id,
            'certification_id' => $certification->id,
            'uuid' => fake()->uuid(),
            'issued_at' => now(),
        ]);

        $this->createBadge([
            'key' => 'community_architect',
            'type' => 'creator',
            'name' => 'Community Architect',
            'condition_type' => 'certified_completions',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 210,
        ]);

        $this->service->evaluate($owner);

        $this->assertDatabaseHas('user_badges', ['user_id' => $owner->id]);
    }

    // ─── evaluate: pinned_posts ───────────────────────────────────────────────

    public function test_evaluate_awards_pinned_posts_badge(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        Post::factory()->create([
            'community_id' => $community->id,
            'user_id' => User::factory()->create()->id,
            'is_pinned' => true,
        ]);

        $this->createBadge([
            'key' => 'the_curator',
            'type' => 'creator',
            'name' => 'The Curator',
            'condition_type' => 'pinned_posts',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 230,
        ]);

        $this->service->evaluate($owner);

        $this->assertDatabaseHas('user_badges', ['user_id' => $owner->id]);
    }

    // ─── evaluate: total_payout ───────────────────────────────────────────────

    public function test_evaluate_awards_total_payout_badge(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        OwnerPayout::create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'amount' => 100000,
            'status' => 'succeeded',
        ]);

        $this->createBadge([
            'key' => 'revenue_titan',
            'type' => 'creator',
            'name' => 'Revenue Titan',
            'condition_type' => 'total_payout',
            'condition_value' => 100000,
            'community_id' => null,
            'sort_order' => 240,
        ]);

        $this->service->evaluate($owner);

        $this->assertDatabaseHas('user_badges', ['user_id' => $owner->id]);
    }

    // ─── evaluate: affiliate_overlord ─────────────────────────────────────────

    public function test_evaluate_awards_affiliate_overlord_badge(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        for ($i = 0; $i < 2; $i++) {
            $aff = Affiliate::create([
                'community_id' => $community->id,
                'user_id' => User::factory()->create()->id,
                'code' => "OVERLORD{$i}",
                'status' => Affiliate::STATUS_ACTIVE,
            ]);
            AffiliateConversion::create([
                'affiliate_id' => $aff->id,
                'subscription_id' => Subscription::factory()->create(['community_id' => $community->id])->id,
                'referred_user_id' => User::factory()->create()->id,
                'sale_amount' => 499,
                'platform_fee' => 74.85,
                'commission_amount' => 42.42,
                'creator_amount' => 381.73,
                'status' => AffiliateConversion::STATUS_PAID,
            ]);
        }

        $this->createBadge([
            'key' => 'affiliate_overlord',
            'type' => 'creator',
            'name' => 'Affiliate Overlord',
            'condition_type' => 'affiliate_overlord',
            'condition_value' => 2,
            'community_id' => null,
            'sort_order' => 220,
        ]);

        $this->service->evaluate($owner);

        $this->assertDatabaseHas('user_badges', ['user_id' => $owner->id]);
    }

    // ─── evaluate: already earned ─────────────────────────────────────────────

    public function test_evaluate_does_not_double_award_badge(): void
    {
        $user = User::factory()->create();

        $badge = $this->createBadge([
            'key' => 'pioneer_member',
            'type' => 'member',
            'name' => 'Pioneer',
            'condition_type' => 'pioneer_member',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 10,
        ]);

        UserBadge::create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'community_id' => null,
            'earned_at' => now(),
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseCount('user_badges', 1);
    }

    // ─── evaluate: unimplemented condition types ──────────────────────────────

    public function test_evaluate_skips_unimplemented_conditions(): void
    {
        $user = User::factory()->create();

        $this->createBadge([
            'key' => 'seven_day_streak',
            'type' => 'member',
            'name' => 'Seven Day Streak',
            'condition_type' => 'seven_day_streak',
            'condition_value' => 7,
            'community_id' => null,
            'sort_order' => 30,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseMissing('user_badges', ['user_id' => $user->id]);
    }

    public function test_evaluate_skips_unknown_condition_type(): void
    {
        $user = User::factory()->create();

        $this->createBadge([
            'key' => 'unknown_badge',
            'type' => 'member',
            'name' => 'Unknown',
            'condition_type' => 'totally_unknown',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 999,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseMissing('user_badges', ['user_id' => $user->id]);
    }

    // ─── evaluate: token awarding ─────────────────────────────────────────────

    public function test_token_award_only_for_configured_badges(): void
    {
        $user = User::factory()->create(['crz_token_balance' => 0]);

        $this->createBadge([
            'key' => 'pioneer_member',
            'type' => 'member',
            'name' => 'Pioneer',
            'condition_type' => 'pioneer_member',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 10,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id]);
        $this->assertDatabaseCount('crz_token_transactions', 0);
        $this->assertEquals(0, $user->fresh()->crz_token_balance);
    }

    // ─── evaluate with global badges (no communityId) ─────────────────────────

    public function test_evaluate_without_community_id_checks_global_badges(): void
    {
        $user = User::factory()->create();

        $this->createBadge([
            'key' => 'pioneer_member',
            'type' => 'member',
            'name' => 'Pioneer',
            'condition_type' => 'pioneer_member',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 10,
        ]);

        $otherCommunity = Community::factory()->create();
        $this->createBadge([
            'key' => 'community_specific',
            'type' => 'member',
            'name' => 'Community',
            'condition_type' => 'lessons_completed',
            'condition_value' => 1,
            'community_id' => $otherCommunity->id,
            'sort_order' => 20,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseCount('user_badges', 1);
    }

    // ─── evaluate: pioneer_creator ────────────────────────────────────────────

    public function test_evaluate_awards_pioneer_creator_with_100_subs(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        for ($i = 0; $i < 100; $i++) {
            Subscription::factory()->create([
                'community_id' => $community->id,
                'status' => 'active',
            ]);
        }

        $this->createBadge([
            'key' => 'pioneer_creator',
            'type' => 'creator',
            'name' => 'The Pioneer Creator',
            'condition_type' => 'pioneer_creator',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 200,
        ]);

        $this->service->evaluate($owner);

        $this->assertDatabaseHas('user_badges', ['user_id' => $owner->id]);
    }

    public function test_pioneer_creator_not_awarded_without_community(): void
    {
        $user = User::factory()->create();

        $this->createBadge([
            'key' => 'pioneer_creator',
            'type' => 'creator',
            'name' => 'Pioneer Creator',
            'condition_type' => 'pioneer_creator',
            'condition_value' => 1,
            'community_id' => null,
            'sort_order' => 200,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseMissing('user_badges', ['user_id' => $user->id]);
    }

    // ─── evaluate: lessons_completed global ───────────────────────────────────

    public function test_lessons_completed_global_counts_all_communities(): void
    {
        $user = User::factory()->create();
        $community1 = Community::factory()->create();
        $community2 = Community::factory()->create();

        $course1 = Course::create(['community_id' => $community1->id, 'title' => 'C1']);
        $module1 = CourseModule::create(['course_id' => $course1->id, 'title' => 'M1', 'position' => 1]);
        $lesson1 = CourseLesson::create(['module_id' => $module1->id, 'title' => 'L1', 'position' => 1]);

        $course2 = Course::create(['community_id' => $community2->id, 'title' => 'C2']);
        $module2 = CourseModule::create(['course_id' => $course2->id, 'title' => 'M2', 'position' => 1]);
        $lesson2 = CourseLesson::create(['module_id' => $module2->id, 'title' => 'L2', 'position' => 1]);

        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson1->id]);
        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson2->id]);

        $this->createBadge([
            'key' => 'two_lessons',
            'type' => 'member',
            'name' => 'Two Lessons',
            'condition_type' => 'lessons_completed',
            'condition_value' => 2,
            'community_id' => null,
            'sort_order' => 1,
        ]);

        $this->service->evaluate($user);

        $this->assertDatabaseHas('user_badges', ['user_id' => $user->id]);
    }
}
