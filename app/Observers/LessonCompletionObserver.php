<?php

namespace App\Observers;

use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Support\CacheKeys;

class LessonCompletionObserver
{
    public function created(LessonCompletion $completion): void
    {
        // Traverse: lesson → module → course → community
        $completion->loadMissing('lesson.module.course');
        $communityId = $completion->lesson?->module?->course?->community_id;

        if (! $communityId) return;

        $member = CommunityMember::where('community_id', $communityId)
            ->where('user_id', $completion->user_id)
            ->first();

        $member?->awardPoints(CommunityMember::POINTS_LESSON);

        CacheKeys::flushLeaderboard($communityId);
    }
}
