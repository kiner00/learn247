<?php

namespace App\Queries\AI;

use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserBadge;

class BuildAIContext
{
    public function execute(User $user): array
    {
        $memberships = CommunityMember::where('user_id', $user->id)
            ->with('community.courses.lessons.quiz')
            ->get();

        if ($memberships->isEmpty()) {
            return ['name' => $user->name, 'email' => $user->email, 'communities' => []];
        }

        $completedLessonIds = LessonCompletion::where('user_id', $user->id)->pluck('lesson_id')->all();

        $quizAttempts = QuizAttempt::where('user_id', $user->id)
            ->with('quiz')
            ->get()
            ->groupBy('quiz_id')
            ->map(fn ($attempts) => $attempts->sortByDesc('score')->first());

        $earnedBadges = UserBadge::where('user_id', $user->id)
            ->with('badge')
            ->get()
            ->groupBy('community_id');

        $communities = [];
        foreach ($memberships as $membership) {
            $community  = $membership->community;
            $allLessons = $community->courses->flatMap->lessons;
            $lessonsDone    = $allLessons->whereIn('id', $completedLessonIds);
            $lessonsPending = $allLessons->whereNotIn('id', $completedLessonIds)->take(5);

            $quizData = [];
            foreach ($allLessons as $lesson) {
                if ($lesson->quiz) {
                    $attempt    = $quizAttempts->get($lesson->quiz->id);
                    $quizData[] = [
                        'title'     => $lesson->quiz->title,
                        'attempted' => (bool) $attempt,
                        'passed'    => $attempt?->passed ?? false,
                        'score'     => $attempt?->score ?? 0,
                    ];
                }
            }

            $badges = ($earnedBadges->get($community->id) ?? collect())
                ->map(fn ($ub) => $ub->badge->name)
                ->values()
                ->all();

            $communities[] = [
                'name'                  => $community->name,
                'role'                  => $membership->role,
                'points'                => $membership->points,
                'level'                 => CommunityMember::computeLevel($membership->points),
                'lessons_done'          => $lessonsDone->count(),
                'lessons_total'         => $allLessons->count(),
                'lessons_pending_names' => $lessonsPending->pluck('title')->all(),
                'quizzes'               => $quizData,
                'badges'                => $badges,
            ];
        }

        return ['name' => $user->name, 'email' => $user->email, 'communities' => $communities];
    }
}
