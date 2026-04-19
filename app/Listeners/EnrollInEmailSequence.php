<?php

namespace App\Listeners;

use App\Events\CartAbandoned;
use App\Events\CourseEnrolled;
use App\Events\MemberJoined;
use App\Events\MemberTagged;
use App\Events\SubscriptionPaid;
use App\Models\CommunityMember;
use App\Models\EmailSequence;
use App\Models\EmailSequenceEnrollment;
use App\Models\EmailUnsubscribe;
use Illuminate\Contracts\Queue\ShouldQueue;

class EnrollInEmailSequence implements ShouldQueue
{
    public function handleMemberJoined(MemberJoined $event): void
    {
        $this->enroll(
            $event->member,
            EmailSequence::TRIGGER_MEMBER_JOINED,
            ['membership_type' => $event->member->membership_type]
        );
    }

    public function handleSubscriptionPaid(SubscriptionPaid $event): void
    {
        $this->enroll(
            $event->member,
            EmailSequence::TRIGGER_SUBSCRIPTION_PAID,
            []
        );
    }

    public function handleCourseEnrolled(CourseEnrolled $event): void
    {
        $this->enroll(
            $event->member,
            EmailSequence::TRIGGER_COURSE_ENROLLED,
            ['course_id' => $event->courseId]
        );
    }

    public function handleCartAbandoned(CartAbandoned $event): void
    {
        $cartEvent = $event->cartEvent;

        // Find the member for this user in this community
        $member = CommunityMember::where('community_id', $cartEvent->community_id)
            ->where('user_id', $cartEvent->user_id)
            ->first();

        if (! $member) {
            return;
        }

        $this->enroll(
            $member,
            EmailSequence::TRIGGER_CART_ABANDONED,
            []
        );
    }

    public function handleMemberTagged(MemberTagged $event): void
    {
        $this->enroll(
            $event->member,
            EmailSequence::TRIGGER_TAG_ADDED,
            ['tag_id' => $event->tag->id]
        );
    }

    private function enroll(CommunityMember $member, string $triggerEvent, array $context): void
    {
        // Skip if member is unsubscribed
        $isUnsubscribed = EmailUnsubscribe::where('community_id', $member->community_id)
            ->where('user_id', $member->user_id)
            ->exists();

        if ($isUnsubscribed) {
            return;
        }

        // Find active sequences matching this trigger
        $sequences = EmailSequence::where('community_id', $member->community_id)
            ->where('trigger_event', $triggerEvent)
            ->where('status', EmailSequence::STATUS_ACTIVE)
            ->with('steps')
            ->get();

        foreach ($sequences as $sequence) {
            // Check trigger filter
            if (! $this->matchesFilter($sequence->trigger_filter, $context, $member)) {
                continue;
            }

            // Skip if already enrolled
            $alreadyEnrolled = EmailSequenceEnrollment::where('sequence_id', $sequence->id)
                ->where('community_member_id', $member->id)
                ->exists();

            if ($alreadyEnrolled) {
                continue;
            }

            $firstStep = $sequence->steps->first();

            if (! $firstStep) {
                continue;
            }

            // Calculate when to send the first step
            $nextSendAt = now()->addHours($firstStep->delay_hours);

            EmailSequenceEnrollment::create([
                'sequence_id' => $sequence->id,
                'community_member_id' => $member->id,
                'current_step_id' => $firstStep->id,
                'steps_completed' => 0,
                'status' => EmailSequenceEnrollment::STATUS_ACTIVE,
                'next_send_at' => $nextSendAt,
                'enrolled_at' => now(),
            ]);
        }
    }

    private function matchesFilter(?array $filter, array $context, CommunityMember $member): bool
    {
        if (empty($filter)) {
            return true;
        }

        foreach ($filter as $key => $value) {
            if ($key === 'membership_type' && $member->membership_type !== $value) {
                return false;
            }
            if ($key === 'course_id' && ($context['course_id'] ?? null) != $value) {
                return false;
            }
            if ($key === 'tag_id' && ($context['tag_id'] ?? null) != $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Map events to handler methods.
     */
    public function subscribe($events): array
    {
        return [
            MemberJoined::class => 'handleMemberJoined',
            SubscriptionPaid::class => 'handleSubscriptionPaid',
            CourseEnrolled::class => 'handleCourseEnrolled',
            CartAbandoned::class => 'handleCartAbandoned',
            MemberTagged::class => 'handleMemberTagged',
        ];
    }
}
