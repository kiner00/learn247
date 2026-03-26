<?php

namespace App\Ai\Tools;

use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Models\QuizAttempt;
use App\Models\UserBadge;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetUserProgressTool implements Tool
{
    public function __construct(private int $userId) {}

    public function description(): string
    {
        return "Get the user's detailed learning progress in a specific community: lessons completed, pending lessons, quiz results, badges, points, and level.";
    }

    public function handle(Request $request): string
    {
        $communityName = trim($request->string('community', ''));

        $membership = CommunityMember::where('user_id', $this->userId)
            ->whereHas('community', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($communityName) . '%']))
            ->with('community.courses.modules.lessons.quiz')
            ->first();

        if (! $membership) {
            return "User is not a member of a community matching \"{$communityName}\".";
        }

        $community = $membership->community;
        $allLessons = $community->courses->flatMap(fn ($c) => $c->modules->flatMap->lessons);

        $completedIds = LessonCompletion::where('user_id', $this->userId)->pluck('lesson_id')->all();

        $quizAttempts = QuizAttempt::where('user_id', $this->userId)
            ->get()
            ->groupBy('quiz_id')
            ->map(fn ($attempts) => $attempts->sortByDesc('score')->first());

        $quizData = [];
        foreach ($allLessons as $lesson) {
            if ($lesson->quiz) {
                $attempt = $quizAttempts->get($lesson->quiz->id);
                $quizData[] = [
                    'quiz'      => $lesson->quiz->title,
                    'lesson'    => $lesson->title,
                    'attempted' => (bool) $attempt,
                    'passed'    => $attempt?->passed ?? false,
                    'score'     => $attempt?->score ?? 0,
                ];
            }
        }

        $badges = UserBadge::where('user_id', $this->userId)
            ->where('community_id', $community->id)
            ->with('badge:id,name')
            ->get()
            ->pluck('badge.name')
            ->filter()
            ->values()
            ->all();

        $pending = $allLessons->whereNotIn('id', $completedIds)->take(5)->pluck('title')->values()->all();

        return json_encode([
            'community'      => $community->name,
            'role'           => $membership->role,
            'points'         => $membership->points,
            'level'          => CommunityMember::computeLevel($membership->points),
            'lessons_done'   => count($completedIds),
            'lessons_total'  => $allLessons->count(),
            'pending_lessons' => $pending,
            'quizzes'        => $quizData,
            'badges'         => $badges,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'community' => $schema->string()->description('The name (or partial name) of the community to get progress for.')->required(),
        ];
    }
}
