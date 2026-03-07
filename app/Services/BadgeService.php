<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    /**
     * Evaluate and award all applicable badges for a user within a community context.
     * Call this after significant events (lesson complete, post created, quiz passed).
     */
    public function evaluate(User $user, ?int $communityId = null): void
    {
        $badges = Badge::where(function ($q) use ($communityId) {
            $q->whereNull('community_id');
            if ($communityId) {
                $q->orWhere('community_id', $communityId);
            }
        })->get();

        foreach ($badges as $badge) {
            if ($this->alreadyEarned($user->id, $badge->id, $communityId)) {
                continue;
            }

            if ($this->conditionMet($user, $badge, $communityId)) {
                UserBadge::create([
                    'user_id'      => $user->id,
                    'badge_id'     => $badge->id,
                    'community_id' => $communityId,
                    'earned_at'    => now(),
                ]);
            }
        }
    }

    private function alreadyEarned(int $userId, int $badgeId, ?int $communityId): bool
    {
        return UserBadge::where('user_id', $userId)
            ->where('badge_id', $badgeId)
            ->where('community_id', $communityId)
            ->exists();
    }

    private function conditionMet(User $user, Badge $badge, ?int $communityId): bool
    {
        return match ($badge->condition_type) {
            'lessons_completed' => $this->countLessonsCompleted($user->id, $communityId) >= $badge->condition_value,
            'posts_created'     => $user->posts()
                                        ->when($communityId, fn ($q) => $q->where('community_id', $communityId))
                                        ->count() >= $badge->condition_value,
            'level_reached'     => $this->getMemberLevel($user->id, $communityId) >= $badge->condition_value,
            'quiz_passed'       => $this->countQuizzesPassed($user->id, $communityId) >= $badge->condition_value,
            default             => false,
        };
    }

    private function countLessonsCompleted(int $userId, ?int $communityId): int
    {
        $query = LessonCompletion::where('user_id', $userId);

        if ($communityId) {
            // Join through course_lessons → course_modules → courses → communities
            $query->whereHas('lesson.module.course', fn ($q) => $q->where('community_id', $communityId));
        }

        return $query->count();
    }

    private function getMemberLevel(int $userId, ?int $communityId): int
    {
        if (!$communityId) return 1;

        $member = CommunityMember::where('user_id', $userId)
            ->where('community_id', $communityId)
            ->first();

        return $member ? \App\Models\CommunityMember::computeLevel($member->points) : 1;
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

    /**
     * Seed default global badges. Safe to run multiple times — updates existing badges
     * if name/icon/description changed, never deletes user_badges records.
     */
    public static function seedDefaults(): void
    {
        $defaults = [
            ['name' => 'First Step',       'icon' => '🐣', 'condition_type' => 'lessons_completed', 'condition_value' => 1,  'description' => 'Complete your first lesson'],
            ['name' => 'Getting Started',  'icon' => '🚀', 'condition_type' => 'lessons_completed', 'condition_value' => 5,  'description' => 'Complete 5 lessons'],
            ['name' => 'Dedicated',        'icon' => '📚', 'condition_type' => 'lessons_completed', 'condition_value' => 25, 'description' => 'Complete 25 lessons'],
            ['name' => 'First Post',       'icon' => '✍️', 'condition_type' => 'posts_created',     'condition_value' => 1,  'description' => 'Create your first post'],
            ['name' => 'Conversationalist','icon' => '💬', 'condition_type' => 'posts_created',     'condition_value' => 10, 'description' => 'Create 10 posts'],
            ['name' => 'Level 3',          'icon' => '⭐', 'condition_type' => 'level_reached',     'condition_value' => 3,  'description' => 'Reach level 3'],
            ['name' => 'Level 5',          'icon' => '🌟', 'condition_type' => 'level_reached',     'condition_value' => 5,  'description' => 'Reach level 5'],
            ['name' => 'Quiz Ace',         'icon' => '🏆', 'condition_type' => 'quiz_passed',       'condition_value' => 1,  'description' => 'Pass your first quiz'],
            ['name' => 'Quiz Master',      'icon' => '🎯', 'condition_type' => 'quiz_passed',       'condition_value' => 5,  'description' => 'Pass 5 quizzes'],
        ];

        foreach ($defaults as $b) {
            // Match on the logical key (type + value + no community).
            // Update name/icon/description if they changed — never touches user_badges.
            Badge::updateOrCreate(
                [
                    'community_id'    => null,
                    'condition_type'  => $b['condition_type'],
                    'condition_value' => $b['condition_value'],
                ],
                [
                    'name'        => $b['name'],
                    'icon'        => $b['icon'],
                    'description' => $b['description'],
                ]
            );
        }
    }
}
