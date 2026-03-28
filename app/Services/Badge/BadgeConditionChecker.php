<?php

namespace App\Services\Badge;

use App\Models\AffiliateConversion;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Models\OwnerPayout;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserBadge;

class BadgeConditionChecker
{
    public function conditionMet(User $user, Badge $badge, ?int $communityId): bool
    {
        return match ($badge->condition_type) {
            'lessons_completed' => $this->countLessonsCompleted($user->id, $communityId) >= $badge->condition_value,
            'posts_created'     => $user->posts()
                                        ->when($communityId, fn ($q) => $q->where('community_id', $communityId))
                                        ->count() >= $badge->condition_value,
            'level_reached'     => $this->getMemberLevel($user->id, $communityId) >= $badge->condition_value,
            'quiz_passed'       => $this->countQuizzesPassed($user->id, $communityId) >= $badge->condition_value,

            'early_bird'            => $this->isEarlyBird($user),
            'early_builder'         => $this->isEarlyBuilder($user),
            'pioneer_member'        => $this->isPioneerMember($user),
            'course_crusader'       => $this->hasCompletedCourseIn30Days($user->id),
            'affiliate_referrals'   => $this->countPaidReferrals($user->id) >= $badge->condition_value,
            'affiliate_commission'  => $this->totalAffiliateCommission($user->id) >= $badge->condition_value,
            'pioneer_creator'       => $this->isPioneerCreator($user),
            'certified_completions' => $this->countCertifiedCompletions($user->id) >= $badge->condition_value,
            'affiliate_overlord'    => $this->countActiveAffiliatesWithSale($user->id) >= $badge->condition_value,
            'pinned_posts'          => $this->countPinnedPosts($user->id) >= $badge->condition_value,
            'total_payout'          => $this->totalOwnerPayout($user->id) >= $badge->condition_value,

            'seven_day_streak', 'helpful_reaction', 'solution_accepted',
            'social_connector', 'bridge_builder' => false,

            default => false,
        };
    }

    private function countLessonsCompleted(int $userId, ?int $communityId): int
    {
        $query = LessonCompletion::where('user_id', $userId);

        if ($communityId) {
            $query->whereHas('lesson.module.course', fn ($q) => $q->where('community_id', $communityId));
        }

        return $query->count();
    }

    private function getMemberLevel(int $userId, ?int $communityId): int
    {
        if (! $communityId) return 1;

        $member = CommunityMember::where('user_id', $userId)
            ->where('community_id', $communityId)
            ->first();

        return $member ? CommunityMember::computeLevel($member->points) : 1;
    }

    private function countQuizzesPassed(int $userId, ?int $communityId): int
    {
        return \App\Models\QuizAttempt::where('user_id', $userId)
            ->where('passed', true)
            ->when($communityId, function ($q) use ($communityId) {
                $q->whereHas('quiz.lesson.module.course', fn ($q2) => $q2->where('community_id', $communityId));
            })
            ->distinct('quiz_id')
            ->count('quiz_id');
    }

    private function isEarlyBird(User $user): bool
    {
        if ($this->countPaidReferrals($user->id) < 1) return false;

        $earlyBirdBadgeId = Badge::where('key', 'early_bird')->value('id');
        if (! $earlyBirdBadgeId) return false;

        return UserBadge::where('badge_id', $earlyBirdBadgeId)->count() < 100000;
    }

    private function isEarlyBuilder(User $user): bool
    {
        $ownedCommunityIds = Community::where('owner_id', $user->id)->pluck('id');
        if ($ownedCommunityIds->isEmpty()) return false;

        $hasEnoughPayingMembers = Subscription::whereIn('community_id', $ownedCommunityIds)
            ->where('status', 'active')
            ->count() >= 10;

        if (! $hasEnoughPayingMembers) return false;

        $earlyBuilderBadgeId = Badge::where('key', 'early_builder')->value('id');
        if (! $earlyBuilderBadgeId) return false;

        return UserBadge::where('badge_id', $earlyBuilderBadgeId)->count() < 1000;
    }

    private function isPioneerMember(User $user): bool
    {
        return User::where('id', '<=', $user->id)->count() <= 100000;
    }

    private function hasCompletedCourseIn30Days(int $userId): bool
    {
        $courses = \App\Models\Course::whereHas('modules.lessons.completions', fn ($q) => $q->where('user_id', $userId))
            ->with('modules.lessons')
            ->get();

        foreach ($courses as $course) {
            $lessonIds    = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
            $totalLessons = $lessonIds->count();
            if ($totalLessons === 0) continue;

            $completions = LessonCompletion::where('user_id', $userId)
                ->whereIn('lesson_id', $lessonIds)
                ->orderBy('created_at')
                ->pluck('created_at');

            if ($completions->count() >= $totalLessons) {
                if ($completions->first()->diffInDays($completions->last()) <= 30) {
                    return true;
                }
            }
        }

        return false;
    }

    private function countPaidReferrals(int $userId): int
    {
        return AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('user_id', $userId))
            ->where('status', AffiliateConversion::STATUS_PAID)
            ->count();
    }

    private function totalAffiliateCommission(int $userId): float
    {
        return (float) AffiliateConversion::whereHas('affiliate', fn ($q) => $q->where('user_id', $userId))
            ->sum('commission_amount');
    }

    private function isPioneerCreator(User $user): bool
    {
        $ownedCommunityIds = Community::where('owner_id', $user->id)->pluck('id');
        if ($ownedCommunityIds->isEmpty()) return false;

        $has100Subs = Subscription::whereIn('community_id', $ownedCommunityIds)
            ->where('status', 'active')
            ->count() >= 100;

        if (! $has100Subs) return false;

        $pioneerCreatorIds = Community::selectRaw('owner_id, count(*) as active_subs')
            ->join('subscriptions', 'subscriptions.community_id', '=', 'communities.id')
            ->where('subscriptions.status', 'active')
            ->groupBy('owner_id')
            ->having('active_subs', '>=', 100)
            ->orderBy('owner_id')
            ->limit(1000)
            ->pluck('owner_id');

        return $pioneerCreatorIds->contains($user->id);
    }

    private function countCertifiedCompletions(int $userId): int
    {
        return Certificate::whereHas('certification.community', fn ($q) => $q->where('owner_id', $userId))->count();
    }

    private function countActiveAffiliatesWithSale(int $userId): int
    {
        return \App\Models\Affiliate::whereHas('community', fn ($q) => $q->where('owner_id', $userId))
            ->whereHas('conversions', fn ($q) => $q->where('status', AffiliateConversion::STATUS_PAID))
            ->count();
    }

    private function countPinnedPosts(int $userId): int
    {
        return Post::whereHas('community', fn ($q) => $q->where('owner_id', $userId))
            ->where('is_pinned', true)
            ->count();
    }

    private function totalOwnerPayout(int $userId): float
    {
        return (float) OwnerPayout::where('user_id', $userId)
            ->where('status', 'succeeded')
            ->sum('amount');
    }
}
