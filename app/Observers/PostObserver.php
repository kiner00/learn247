<?php

namespace App\Observers;

use App\Models\CommunityMember;
use App\Models\Post;
use App\Support\CacheKeys;

class PostObserver
{
    public function created(Post $post): void
    {
        $this->adjustPoints($post, CommunityMember::POINTS_POST);
    }

    public function deleted(Post $post): void
    {
        $this->adjustPoints($post, -CommunityMember::POINTS_POST);
    }

    private function adjustPoints(Post $post, int $pts): void
    {
        $member = CommunityMember::where('community_id', $post->community_id)
            ->where('user_id', $post->user_id)
            ->first();

        if (! $member) {
            return;
        }

        if ($pts > 0) {
            $member->awardPoints($pts);
        } else {
            $member->deductPoints(abs($pts));
        }

        CacheKeys::flushLeaderboard($post->community_id);
    }
}
