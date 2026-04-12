<?php

namespace Tests\Feature\Listeners;

use App\Events\CartAbandoned;
use App\Events\CourseEnrolled;
use App\Events\MemberJoined;
use App\Events\MemberTagged;
use App\Events\SubscriptionPaid;
use App\Listeners\EnrollInEmailSequence;
use App\Models\CartEvent;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailCampaign;
use App\Models\EmailSequence;
use App\Models\EmailSequenceEnrollment;
use App\Models\EmailSequenceStep;
use App\Models\EmailUnsubscribe;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollInEmailSequenceTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveSequence(Community $community, string $trigger, ?array $filter = null): array
    {
        $campaign = EmailCampaign::create([
            'community_id' => $community->id,
            'name'         => 'Sequence Campaign',
            'type'         => EmailCampaign::TYPE_SEQUENCE,
            'status'       => 'draft',
        ]);

        $sequence = EmailSequence::create([
            'campaign_id'    => $campaign->id,
            'community_id'   => $community->id,
            'trigger_event'  => $trigger,
            'trigger_filter' => $filter,
            'status'         => EmailSequence::STATUS_ACTIVE,
        ]);

        $step = EmailSequenceStep::create([
            'sequence_id' => $sequence->id,
            'position'    => 1,
            'delay_hours' => 2,
            'subject'     => 'Welcome!',
            'html_body'   => '<p>Hello</p>',
        ]);

        return compact('campaign', 'sequence', 'step');
    }

    // ── handleMemberJoined ──────────────────────────────────────────────────

    public function test_enrolls_member_on_member_joined(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $data = $this->createActiveSequence($community, EmailSequence::TRIGGER_MEMBER_JOINED);

        $listener = new EnrollInEmailSequence();
        $listener->handleMemberJoined(new MemberJoined($member));

        $this->assertDatabaseHas('email_sequence_enrollments', [
            'sequence_id'         => $data['sequence']->id,
            'community_member_id' => $member->id,
            'status'              => EmailSequenceEnrollment::STATUS_ACTIVE,
            'steps_completed'     => 0,
            'current_step_id'     => $data['step']->id,
        ]);
    }

    public function test_does_not_enroll_unsubscribed_member(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $this->createActiveSequence($community, EmailSequence::TRIGGER_MEMBER_JOINED);

        EmailUnsubscribe::create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'unsubscribed_at' => now(),
        ]);

        $listener = new EnrollInEmailSequence();
        $listener->handleMemberJoined(new MemberJoined($member));

        $this->assertDatabaseCount('email_sequence_enrollments', 0);
    }

    public function test_does_not_double_enroll(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $data = $this->createActiveSequence($community, EmailSequence::TRIGGER_MEMBER_JOINED);

        $listener = new EnrollInEmailSequence();
        $listener->handleMemberJoined(new MemberJoined($member));
        $listener->handleMemberJoined(new MemberJoined($member));

        $this->assertDatabaseCount('email_sequence_enrollments', 1);
    }

    public function test_skips_draft_sequences(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $data = $this->createActiveSequence($community, EmailSequence::TRIGGER_MEMBER_JOINED);
        $data['sequence']->update(['status' => EmailSequence::STATUS_DRAFT]);

        $listener = new EnrollInEmailSequence();
        $listener->handleMemberJoined(new MemberJoined($member));

        $this->assertDatabaseCount('email_sequence_enrollments', 0);
    }

    public function test_skips_sequence_with_no_steps(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $data = $this->createActiveSequence($community, EmailSequence::TRIGGER_MEMBER_JOINED);
        // Remove all steps
        EmailSequenceStep::where('sequence_id', $data['sequence']->id)->delete();

        $listener = new EnrollInEmailSequence();
        $listener->handleMemberJoined(new MemberJoined($member));

        $this->assertDatabaseCount('email_sequence_enrollments', 0);
    }

    // ── Trigger filter matching ─────────────────────────────────────────────

    public function test_membership_type_filter_matches(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'membership_type' => 'free',
        ]);

        $this->createActiveSequence($community, EmailSequence::TRIGGER_MEMBER_JOINED, [
            'membership_type' => 'free',
        ]);

        $listener = new EnrollInEmailSequence();
        $listener->handleMemberJoined(new MemberJoined($member));

        $this->assertDatabaseCount('email_sequence_enrollments', 1);
    }

    public function test_membership_type_filter_rejects_mismatch(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'membership_type' => 'free',
        ]);

        $this->createActiveSequence($community, EmailSequence::TRIGGER_MEMBER_JOINED, [
            'membership_type' => 'paid',
        ]);

        $listener = new EnrollInEmailSequence();
        $listener->handleMemberJoined(new MemberJoined($member));

        $this->assertDatabaseCount('email_sequence_enrollments', 0);
    }

    // ── handleSubscriptionPaid ──────────────────────────────────────────────

    public function test_enrolls_on_subscription_paid(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $data = $this->createActiveSequence($community, EmailSequence::TRIGGER_SUBSCRIPTION_PAID);

        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'sub_test_123',
            'status'       => 'active',
        ]);

        $listener = new EnrollInEmailSequence();
        $listener->handleSubscriptionPaid(new SubscriptionPaid($member, $subscription));

        $this->assertDatabaseHas('email_sequence_enrollments', [
            'sequence_id'         => $data['sequence']->id,
            'community_member_id' => $member->id,
        ]);
    }

    // ── handleCourseEnrolled ────────────────────────────────────────────────

    public function test_enrolls_on_course_enrolled_with_matching_filter(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $data = $this->createActiveSequence($community, EmailSequence::TRIGGER_COURSE_ENROLLED, [
            'course_id' => 42,
        ]);

        $listener = new EnrollInEmailSequence();
        $listener->handleCourseEnrolled(new CourseEnrolled($member, 42));

        $this->assertDatabaseCount('email_sequence_enrollments', 1);
    }

    public function test_skips_course_enrolled_with_wrong_course_id(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $this->createActiveSequence($community, EmailSequence::TRIGGER_COURSE_ENROLLED, [
            'course_id' => 42,
        ]);

        $listener = new EnrollInEmailSequence();
        $listener->handleCourseEnrolled(new CourseEnrolled($member, 99));

        $this->assertDatabaseCount('email_sequence_enrollments', 0);
    }

    // ── handleCartAbandoned ─────────────────────────────────────────────────

    public function test_enrolls_on_cart_abandoned(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $data = $this->createActiveSequence($community, EmailSequence::TRIGGER_CART_ABANDONED);

        $cartEvent = CartEvent::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'email'          => $user->email,
            'event_type'     => CartEvent::TYPE_ABANDONED,
            'reference_type' => 'subscription',
            'reference_id'   => 1,
        ]);

        $listener = new EnrollInEmailSequence();
        $listener->handleCartAbandoned(new CartAbandoned($cartEvent));

        $this->assertDatabaseHas('email_sequence_enrollments', [
            'sequence_id'         => $data['sequence']->id,
            'community_member_id' => $member->id,
        ]);
    }

    public function test_cart_abandoned_skips_when_no_member_found(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();

        // No CommunityMember created for this user/community

        $this->createActiveSequence($community, EmailSequence::TRIGGER_CART_ABANDONED);

        $cartEvent = CartEvent::create([
            'community_id'   => $community->id,
            'user_id'        => $user->id,
            'email'          => $user->email,
            'event_type'     => CartEvent::TYPE_ABANDONED,
            'reference_type' => 'subscription',
            'reference_id'   => 1,
        ]);

        $listener = new EnrollInEmailSequence();
        $listener->handleCartAbandoned(new CartAbandoned($cartEvent));

        $this->assertDatabaseCount('email_sequence_enrollments', 0);
    }

    // ── handleMemberTagged ──────────────────────────────────────────────────

    public function test_enrolls_on_member_tagged_with_matching_tag(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $tag = Tag::create([
            'community_id' => $community->id,
            'name'         => 'VIP',
            'slug'         => 'vip',
            'type'         => Tag::TYPE_MANUAL,
        ]);

        $data = $this->createActiveSequence($community, EmailSequence::TRIGGER_TAG_ADDED, [
            'tag_id' => $tag->id,
        ]);

        $listener = new EnrollInEmailSequence();
        $listener->handleMemberTagged(new MemberTagged($member, $tag));

        $this->assertDatabaseCount('email_sequence_enrollments', 1);
    }

    public function test_tag_filter_rejects_wrong_tag(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $member    = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $tag = Tag::create([
            'community_id' => $community->id,
            'name'         => 'VIP',
            'slug'         => 'vip',
            'type'         => Tag::TYPE_MANUAL,
        ]);

        $this->createActiveSequence($community, EmailSequence::TRIGGER_TAG_ADDED, [
            'tag_id' => 9999,
        ]);

        $listener = new EnrollInEmailSequence();
        $listener->handleMemberTagged(new MemberTagged($member, $tag));

        $this->assertDatabaseCount('email_sequence_enrollments', 0);
    }

    // ── subscribe() ─────────────────────────────────────────────────────────

    public function test_subscribe_maps_all_events(): void
    {
        $listener = new EnrollInEmailSequence();
        $mapping  = $listener->subscribe(null);

        $this->assertArrayHasKey(MemberJoined::class, $mapping);
        $this->assertArrayHasKey(SubscriptionPaid::class, $mapping);
        $this->assertArrayHasKey(CourseEnrolled::class, $mapping);
        $this->assertArrayHasKey(CartAbandoned::class, $mapping);
        $this->assertArrayHasKey(MemberTagged::class, $mapping);
    }
}
