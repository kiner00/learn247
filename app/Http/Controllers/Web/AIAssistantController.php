<?php

namespace App\Http\Controllers\Web;

use App\Ai\Agents\CommunityAssistant;
use App\Http\Controllers\Controller;
use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Models\QuizAttempt;
use App\Models\UserBadge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIAssistantController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message'         => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
        ]);

        $user = $request->user();

        $memberships = CommunityMember::where('user_id', $user->id)
            ->with('community.courses.lessons.quiz')
            ->get();

        if ($memberships->isEmpty()) {
            return response()->json(['error' => 'You must be a member of a community to use the AI assistant.'], 403);
        }

        $completedLessonIds = LessonCompletion::where('user_id', $user->id)
            ->pluck('lesson_id')
            ->all();

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
            $community = $membership->community;
            $allLessons = $community->courses->flatMap->lessons;
            $lessonsDone = $allLessons->whereIn('id', $completedLessonIds);
            $lessonsPending = $allLessons->whereNotIn('id', $completedLessonIds)->take(5);

            $quizData = [];
            foreach ($allLessons as $lesson) {
                if ($lesson->quiz) {
                    $attempt = $quizAttempts->get($lesson->quiz->id);
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

        $context = [
            'name'        => $user->name,
            'email'       => $user->email,
            'communities' => $communities,
        ];

        $agent = new CommunityAssistant($context);

        if ($request->conversation_id) {
            $response = $agent->continue($request->conversation_id, as: $user)->prompt($request->message);
        } else {
            $response = $agent->forUser($user)->prompt($request->message);
        }

        return response()->json([
            'message'         => $response->text,
            'conversation_id' => $response->conversationId,
        ]);
    }
}
