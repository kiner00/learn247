<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\CommunityMember;

class CommentObserver
{
    public function created(Comment $comment): void
    {
        $this->adjustPoints($comment, CommunityMember::POINTS_COMMENT);
    }

    public function deleted(Comment $comment): void
    {
        $this->adjustPoints($comment, -CommunityMember::POINTS_COMMENT);
    }

    private function adjustPoints(Comment $comment, int $pts): void
    {
        $member = CommunityMember::where('community_id', $comment->community_id)
            ->where('user_id', $comment->user_id)
            ->first();

        if (! $member) return;

        if ($pts > 0) {
            $member->awardPoints($pts);
        } else {
            $member->deductPoints(abs($pts));
        }
    }
}
