<?php

namespace Tests\Feature\Models;

use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CrzTokenTransaction;
use App\Models\DirectMessage;
use App\Models\Event;
use App\Models\LessonCompletion;
use App\Models\Message;
use App\Models\Notification;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    // ── User ──────────────────────────────────────────────

    public function test_user_owned_communities_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->ownedCommunities());
    }

    public function test_user_community_memberships_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->communityMemberships());
    }

    public function test_user_posts_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->posts());
    }

    public function test_user_comments_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->comments());
    }

    public function test_user_subscriptions_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->subscriptions());
    }

    public function test_user_payments_relationship(): void
    {
        $user = User::factory()->create();
        $this->assertInstanceOf(HasMany::class, $user->payments());
    }

    public function test_user_is_super_admin(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->assertTrue($admin->isSuperAdmin());

        $regular = User::factory()->create(['is_super_admin' => false]);
        $this->assertFalse($regular->isSuperAdmin());
    }

    // ── Community ─────────────────────────────────────────

    public function test_community_owner_relationship(): void
    {
        $community = Community::factory()->create();
        $this->assertInstanceOf(BelongsTo::class, $community->owner());
    }

    public function test_community_members_relationship(): void
    {
        $community = Community::factory()->create();
        $this->assertInstanceOf(HasMany::class, $community->members());
    }

    public function test_community_posts_relationship(): void
    {
        $community = Community::factory()->create();
        $this->assertInstanceOf(HasMany::class, $community->posts());
    }

    public function test_community_comments_relationship(): void
    {
        $community = Community::factory()->create();
        $this->assertInstanceOf(HasMany::class, $community->comments());
    }

    public function test_community_subscriptions_relationship(): void
    {
        $community = Community::factory()->create();
        $this->assertInstanceOf(HasMany::class, $community->subscriptions());
    }

    public function test_community_payments_relationship(): void
    {
        $community = Community::factory()->create();
        $this->assertInstanceOf(HasMany::class, $community->payments());
    }

    public function test_community_affiliates_relationship(): void
    {
        $community = Community::factory()->create();
        $this->assertInstanceOf(HasMany::class, $community->affiliates());
    }

    public function test_community_courses_relationship(): void
    {
        $community = Community::factory()->create();
        $this->assertInstanceOf(HasMany::class, $community->courses());
    }

    public function test_community_messages_relationship(): void
    {
        $community = Community::factory()->create();
        $this->assertInstanceOf(HasMany::class, $community->messages());
    }

    public function test_community_events_relationship(): void
    {
        $community = Community::factory()->create();
        $this->assertInstanceOf(HasMany::class, $community->events());
    }

    public function test_community_is_free(): void
    {
        $free = Community::factory()->create(['price' => 0]);
        $this->assertTrue($free->isFree());

        $paid = Community::factory()->create(['price' => 100]);
        $this->assertFalse($paid->isFree());
    }

    public function test_community_has_affiliate_program(): void
    {
        $with = Community::factory()->create(['affiliate_commission_rate' => 20]);
        $this->assertTrue($with->hasAffiliateProgram());

        $without = Community::factory()->create(['affiliate_commission_rate' => null]);
        $this->assertFalse($without->hasAffiliateProgram());
    }

    public function test_community_route_key_name(): void
    {
        $community = new Community();
        $this->assertSame('slug', $community->getRouteKeyName());
    }

    // ── PayoutRequest ─────────────────────────────────────

    public function test_payout_request_user_relationship(): void
    {
        $model = new PayoutRequest();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_payout_request_community_relationship(): void
    {
        $model = new PayoutRequest();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_payout_request_affiliate_relationship(): void
    {
        $model = new PayoutRequest();
        $this->assertInstanceOf(BelongsTo::class, $model->affiliate());
    }

    public function test_payout_request_is_pending(): void
    {
        $pending = new PayoutRequest(['status' => PayoutRequest::STATUS_PENDING]);
        $this->assertTrue($pending->isPending());

        $approved = new PayoutRequest(['status' => PayoutRequest::STATUS_APPROVED]);
        $this->assertFalse($approved->isPending());
    }

    // ── Payment ───────────────────────────────────────────

    public function test_payment_subscription_relationship(): void
    {
        $model = new Payment();
        $this->assertInstanceOf(BelongsTo::class, $model->subscription());
    }

    public function test_payment_community_relationship(): void
    {
        $model = new Payment();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_payment_user_relationship(): void
    {
        $model = new Payment();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    // ── OwnerPayout ───────────────────────────────────────

    public function test_owner_payout_community_relationship(): void
    {
        $model = new OwnerPayout();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_owner_payout_user_relationship(): void
    {
        $model = new OwnerPayout();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    // ── Notification ──────────────────────────────────────

    public function test_notification_user_relationship(): void
    {
        $model = new Notification();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_notification_actor_relationship(): void
    {
        $model = new Notification();
        $this->assertInstanceOf(BelongsTo::class, $model->actor());
    }

    public function test_notification_community_relationship(): void
    {
        $model = new Notification();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_notification_is_read(): void
    {
        $read = new Notification(['read_at' => now()]);
        $this->assertTrue($read->isRead());

        $unread = new Notification(['read_at' => null]);
        $this->assertFalse($unread->isRead());
    }

    // ── Event ─────────────────────────────────────────────

    public function test_event_community_relationship(): void
    {
        $model = new Event();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_event_creator_relationship(): void
    {
        $model = new Event();
        $this->assertInstanceOf(BelongsTo::class, $model->creator());
    }

    // ── DirectMessage ─────────────────────────────────────

    public function test_direct_message_sender_relationship(): void
    {
        $model = new DirectMessage();
        $this->assertInstanceOf(BelongsTo::class, $model->sender());
    }

    public function test_direct_message_receiver_relationship(): void
    {
        $model = new DirectMessage();
        $this->assertInstanceOf(BelongsTo::class, $model->receiver());
    }

    // ── AffiliateConversion ───────────────────────────────

    public function test_affiliate_conversion_affiliate_relationship(): void
    {
        $model = new AffiliateConversion();
        $this->assertInstanceOf(BelongsTo::class, $model->affiliate());
    }

    public function test_affiliate_conversion_subscription_relationship(): void
    {
        $model = new AffiliateConversion();
        $this->assertInstanceOf(BelongsTo::class, $model->subscription());
    }

    public function test_affiliate_conversion_payment_relationship(): void
    {
        $model = new AffiliateConversion();
        $this->assertInstanceOf(BelongsTo::class, $model->payment());
    }

    public function test_affiliate_conversion_referred_user_relationship(): void
    {
        $model = new AffiliateConversion();
        $this->assertInstanceOf(BelongsTo::class, $model->referredUser());
    }

    // ── CommunityMember ───────────────────────────────────

    public function test_community_member_community_relationship(): void
    {
        $model = new CommunityMember();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_community_member_user_relationship(): void
    {
        $model = new CommunityMember();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_community_member_can_moderate(): void
    {
        $admin = new CommunityMember(['role' => CommunityMember::ROLE_ADMIN]);
        $this->assertTrue($admin->canModerate());

        $mod = new CommunityMember(['role' => CommunityMember::ROLE_MODERATOR]);
        $this->assertTrue($mod->canModerate());

        $member = new CommunityMember(['role' => CommunityMember::ROLE_MEMBER]);
        $this->assertFalse($member->canModerate());
    }

    public function test_community_member_is_admin(): void
    {
        $admin = new CommunityMember(['role' => CommunityMember::ROLE_ADMIN]);
        $this->assertTrue($admin->isAdmin());

        $member = new CommunityMember(['role' => CommunityMember::ROLE_MEMBER]);
        $this->assertFalse($member->isAdmin());
    }

    public function test_community_member_is_moderator(): void
    {
        $mod = new CommunityMember(['role' => CommunityMember::ROLE_MODERATOR]);
        $this->assertTrue($mod->isModerator());

        $member = new CommunityMember(['role' => CommunityMember::ROLE_MEMBER]);
        $this->assertFalse($member->isModerator());
    }

    // ── Course ────────────────────────────────────────────

    public function test_course_community_relationship(): void
    {
        $model = new Course();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_course_modules_relationship(): void
    {
        $model = new Course();
        $this->assertInstanceOf(HasMany::class, $model->modules());
    }

    public function test_course_lessons_relationship(): void
    {
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'Test Course', 'position' => 0]);
        $this->assertInstanceOf(HasManyThrough::class, $course->lessons());
    }

    // ── CourseLesson ──────────────────────────────────────

    public function test_course_lesson_module_relationship(): void
    {
        $model = new CourseLesson();
        $this->assertInstanceOf(BelongsTo::class, $model->module());
    }

    public function test_course_lesson_completions_relationship(): void
    {
        $model = new CourseLesson();
        $this->assertInstanceOf(HasMany::class, $model->completions());
    }

    public function test_course_lesson_quiz_relationship(): void
    {
        $model = new CourseLesson();
        $this->assertInstanceOf(HasOne::class, $model->quiz());
    }

    public function test_course_lesson_comments_relationship(): void
    {
        $model = new CourseLesson();
        $this->assertInstanceOf(HasMany::class, $model->comments());
    }

    // ── CrzTokenTransaction ───────────────────────────────

    public function test_crz_token_transaction_user_relationship(): void
    {
        $model = new CrzTokenTransaction();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    // ── LessonCompletion ──────────────────────────────────

    public function test_lesson_completion_user_relationship(): void
    {
        $model = new LessonCompletion();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_lesson_completion_lesson_relationship(): void
    {
        $model = new LessonCompletion();
        $this->assertInstanceOf(BelongsTo::class, $model->lesson());
    }

    // ── Message ───────────────────────────────────────────

    public function test_message_community_relationship(): void
    {
        $model = new Message();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_message_user_relationship(): void
    {
        $model = new Message();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    // ── Quiz ──────────────────────────────────────────────

    public function test_quiz_lesson_relationship(): void
    {
        $model = new Quiz();
        $this->assertInstanceOf(BelongsTo::class, $model->lesson());
    }

    public function test_quiz_questions_relationship(): void
    {
        $model = new Quiz();
        $this->assertInstanceOf(HasMany::class, $model->questions());
    }

    public function test_quiz_attempts_relationship(): void
    {
        $model = new Quiz();
        $this->assertInstanceOf(HasMany::class, $model->attempts());
    }

    // ── QuizAttempt ───────────────────────────────────────

    public function test_quiz_attempt_quiz_relationship(): void
    {
        $model = new QuizAttempt();
        $this->assertInstanceOf(BelongsTo::class, $model->quiz());
    }

    public function test_quiz_attempt_user_relationship(): void
    {
        $model = new QuizAttempt();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    // ── QuizQuestion ──────────────────────────────────────

    public function test_quiz_question_quiz_relationship(): void
    {
        $model = new QuizQuestion();
        $this->assertInstanceOf(BelongsTo::class, $model->quiz());
    }

    public function test_quiz_question_options_relationship(): void
    {
        $model = new QuizQuestion();
        $this->assertInstanceOf(HasMany::class, $model->options());
    }

    // ── Setting ───────────────────────────────────────────

    public function test_setting_set_and_get(): void
    {
        Setting::set('test_key', 'test_value');
        $this->assertSame('test_value', Setting::get('test_key'));
    }

    public function test_setting_get_returns_default_when_not_found(): void
    {
        $this->assertSame('fallback', Setting::get('nonexistent', 'fallback'));
    }

    // ── CommunityMember::computeLevel edge cases ────────────

    public function test_community_member_compute_level_with_negative_points(): void
    {
        $this->assertSame(1, CommunityMember::computeLevel(-1));
    }

    public function test_community_member_compute_level_with_zero_points(): void
    {
        $this->assertSame(1, CommunityMember::computeLevel(0));
    }

    public function test_community_member_compute_level_with_max_points(): void
    {
        $this->assertSame(9, CommunityMember::computeLevel(99999));
    }
}
