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
            ->with('community.courses.modules.lessons.quiz')
            ->get();

        $communities = $memberships->map(function (CommunityMember $membership) use ($user) {
            $community = $membership->community;

            $lessons = $community->courses->flatMap(
                fn ($course) => $course->modules->flatMap(fn ($module) => $module->lessons)
            );

            $lessonIds    = $lessons->pluck('id');
            $completedIds = LessonCompletion::where('user_id', $user->id)
                ->whereIn('lesson_id', $lessonIds)
                ->pluck('lesson_id')
                ->all();

            $pendingNames = $lessons->filter(fn ($l) => ! in_array($l->id, $completedIds))
                ->pluck('title')
                ->values()
                ->all();

            $quizzes = $lessons->filter(fn ($l) => $l->quiz)->map(function ($lesson) use ($user) {
                $quiz    = $lesson->quiz;
                $attempt = QuizAttempt::where('quiz_id', $quiz->id)
                    ->where('user_id', $user->id)
                    ->orderByDesc('score')
                    ->first();

                return [
                    'title'     => $quiz->title,
                    'attempted' => $attempt !== null,
                    'passed'    => $attempt?->passed ?? false,
                    'score'     => $attempt?->score ?? 0,
                ];
            })->values()->all();

            $badges = UserBadge::where('user_id', $user->id)
                ->where('community_id', $community->id)
                ->with('badge:id,name')
                ->get()
                ->pluck('badge.name')
                ->all();

            return [
                'name'                  => $community->name,
                'role'                  => $membership->role,
                'points'                => $membership->points ?? 0,
                'level'                 => $membership->level ?? 1,
                'lessons_done'          => count($completedIds),
                'lessons_total'         => $lessonIds->count(),
                'lessons_pending_names' => $pendingNames,
                'quizzes'               => $quizzes,
                'badges'                => $badges,
            ];
        })->values()->all();

        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'communities' => $communities,
        ];
    }
}
