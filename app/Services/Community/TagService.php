<?php

namespace App\Services\Community;

use App\Models\CommunityMember;
use App\Models\Tag;

class TagService
{
    /**
     * Apply automatic tags to a member based on an event.
     *
     * @param  string  $event  e.g. 'member.joined', 'payment.completed', 'course.enrolled'
     * @param  array   $context  Additional context like ['membership_type' => 'free', 'course_id' => 5]
     */
    public function applyAutoTags(CommunityMember $member, string $event, array $context = []): void
    {
        $autoTags = Tag::where('community_id', $member->community_id)
            ->where('type', Tag::TYPE_AUTOMATIC)
            ->get();

        foreach ($autoTags as $tag) {
            if ($this->matchesRule($tag->auto_rule, $event, $member, $context)) {
                $member->tags()->syncWithoutDetaching([$tag->id => ['tagged_at' => now()]]);
            }
        }
    }

    private function matchesRule(?array $rule, string $event, CommunityMember $member, array $context): bool
    {
        if (empty($rule) || empty($rule['event'])) {
            return false;
        }

        if ($rule['event'] !== $event) {
            return false;
        }

        $filters = $rule['filter'] ?? [];

        foreach ($filters as $key => $value) {
            if ($key === 'membership_type' && $member->membership_type !== $value) {
                return false;
            }
            if ($key === 'course_id' && ($context['course_id'] ?? null) != $value) {
                return false;
            }
        }

        return true;
    }
}
