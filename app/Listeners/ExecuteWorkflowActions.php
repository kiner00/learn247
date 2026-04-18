<?php

namespace App\Listeners;

use App\Events\CourseEnrolled;
use App\Events\MemberJoined;
use App\Events\MemberTagged;
use App\Events\SubscriptionPaid;
use App\Models\CommunityMember;
use App\Models\Tag;
use App\Models\Workflow;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExecuteWorkflowActions implements ShouldQueue
{
    public function handleMemberJoined(MemberJoined $event): void
    {
        $this->run(
            $event->member,
            Workflow::TRIGGER_MEMBER_JOINED,
            ['membership_type' => $event->member->membership_type]
        );
    }

    public function handleSubscriptionPaid(SubscriptionPaid $event): void
    {
        $this->run(
            $event->member,
            Workflow::TRIGGER_SUBSCRIPTION_PAID,
            []
        );
    }

    public function handleCourseEnrolled(CourseEnrolled $event): void
    {
        $this->run(
            $event->member,
            Workflow::TRIGGER_COURSE_ENROLLED,
            ['course_id' => $event->courseId]
        );
    }

    private function run(CommunityMember $member, string $trigger, array $context): void
    {
        $workflows = Workflow::where('community_id', $member->community_id)
            ->where('trigger_event', $trigger)
            ->where('is_active', true)
            ->get();

        foreach ($workflows as $workflow) {
            if (! $this->matchesFilter($workflow->trigger_filter, $context, $member)) {
                continue;
            }

            $this->executeAction($workflow, $member);

            $workflow->increment('run_count');
            $workflow->forceFill(['last_run_at' => now()])->save();
        }
    }

    private function matchesFilter(?array $filter, array $context, CommunityMember $member): bool
    {
        if (empty($filter)) {
            return true;
        }

        foreach ($filter as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if ($key === 'membership_type' && $member->membership_type !== $value) {
                return false;
            }
            if ($key === 'course_id' && ($context['course_id'] ?? null) != $value) {
                return false;
            }
        }

        return true;
    }

    private function executeAction(Workflow $workflow, CommunityMember $member): void
    {
        if ($workflow->action_type !== Workflow::ACTION_APPLY_TAG) {
            return;
        }

        $tagId = $workflow->action_config['tag_id'] ?? null;

        if (! $tagId) {
            return;
        }

        $tag = Tag::where('community_id', $workflow->community_id)
            ->where('id', $tagId)
            ->first();

        if (! $tag) {
            return;
        }

        $alreadyTagged = $member->tags()->where('tag_id', $tag->id)->exists();

        $member->tags()->syncWithoutDetaching([
            $tag->id => ['tagged_at' => now()],
        ]);

        if (! $alreadyTagged) {
            MemberTagged::dispatch($member, $tag);
        }
    }
}
