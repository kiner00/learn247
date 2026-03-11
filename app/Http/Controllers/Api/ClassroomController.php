<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Certificate;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CommunityMember;
use App\Models\LessonCompletion;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subscription;
use App\Services\BadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassroomController extends Controller
{
    public function courses(Request $request, Community $community): JsonResponse
    {
        $this->requireMembership($request, $community);

        $userId  = $request->user()->id;
        $courses = $community->courses()->with('modules.lessons')->get();

        $data = $courses->map(function ($course) use ($userId) {
            $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
            $total     = $lessonIds->count();
            $completed = $total > 0
                ? LessonCompletion::where('user_id', $userId)->whereIn('lesson_id', $lessonIds)->count()
                : 0;

            return [
                'id'          => $course->id,
                'title'       => $course->title,
                'description' => $course->description,
                'position'    => $course->position,
                'total'       => $total,
                'completed'   => $completed,
                'progress'    => $total > 0 ? round($completed / $total * 100) : 0,
            ];
        });

        return response()->json(['courses' => $data]);
    }

    public function course(Request $request, Community $community, Course $course): JsonResponse
    {
        $this->requireMembership($request, $community);

        $userId = $request->user()->id;
        $course->load('modules.lessons.quiz.questions.options');

        $lessonIds    = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
        $completedIds = LessonCompletion::where('user_id', $userId)
            ->whereIn('lesson_id', $lessonIds)
            ->pluck('lesson_id')
            ->all();

        $total    = $lessonIds->count();
        $progress = $total > 0 ? round(count($completedIds) / $total * 100) : 0;

        $quizAttempts = QuizAttempt::where('user_id', $userId)
            ->whereHas('quiz', fn ($q) => $q->whereIn('lesson_id', $lessonIds))
            ->get()
            ->groupBy('quiz_id')
            ->map(fn ($attempts) => $attempts->sortByDesc('score')->first())
            ->map(fn ($a) => [
                'score'  => $a->score,
                'passed' => $a->passed,
            ]);

        $certificate = Certificate::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->first();

        $modules = $course->modules->map(fn ($module) => [
            'id'       => $module->id,
            'title'    => $module->title,
            'position' => $module->position,
            'lessons'  => $module->lessons->map(fn ($lesson) => [
                'id'           => $lesson->id,
                'title'        => $lesson->title,
                'position'     => $lesson->position,
                'video_url'    => $lesson->video_url,
                'content'      => $lesson->content,
                'completed'    => in_array($lesson->id, $completedIds),
                'quiz'         => $lesson->quiz ? [
                    'id'         => $lesson->quiz->id,
                    'title'      => $lesson->quiz->title,
                    'pass_score' => $lesson->quiz->pass_score,
                    'questions'  => $lesson->quiz->questions->map(fn ($q) => [
                        'id'       => $q->id,
                        'question' => $q->question,
                        'type'     => $q->type,
                        'options'  => $q->options->map(fn ($o) => [
                            'id'    => $o->id,
                            'label' => $o->label,
                        ]),
                    ]),
                    'best_attempt' => $quizAttempts[$lesson->quiz->id] ?? null,
                ] : null,
            ])->values(),
        ])->values();

        return response()->json([
            'course'      => [
                'id'          => $course->id,
                'title'       => $course->title,
                'description' => $course->description,
            ],
            'modules'     => $modules,
            'progress'    => $progress,
            'certificate' => $certificate ? ['uuid' => $certificate->uuid] : null,
        ]);
    }

    public function completeLesson(Request $request, Community $community, Course $course, CourseLesson $lesson): JsonResponse
    {
        $user = $request->user();

        LessonCompletion::firstOrCreate([
            'user_id'   => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        app(BadgeService::class)->evaluate($user, $community->id);

        return response()->json(['message' => 'Lesson marked as complete!']);
    }

    public function submitQuiz(Request $request, Community $community, Course $course, CourseLesson $lesson, Quiz $quiz): JsonResponse
    {
        $request->validate([
            'answers'   => ['required', 'array'],
            'answers.*' => ['required', 'integer'],
        ]);

        $quiz->load('questions.options');

        $total   = $quiz->questions->count();
        $correct = 0;

        foreach ($quiz->questions as $question) {
            $selectedId = $request->answers[$question->id] ?? null;
            $correctOpt = $question->options->firstWhere('is_correct', true);

            if ($selectedId && $correctOpt && (int) $selectedId === $correctOpt->id) {
                $correct++;
            }
        }

        $score  = $total > 0 ? (int) round($correct / $total * 100) : 0;
        $passed = $score >= $quiz->pass_score;

        QuizAttempt::create([
            'quiz_id'      => $quiz->id,
            'user_id'      => $request->user()->id,
            'answers'      => $request->answers,
            'score'        => $score,
            'passed'       => $passed,
            'completed_at' => now(),
        ]);

        if ($passed) {
            app(BadgeService::class)->evaluate($request->user(), $community->id);
        }

        return response()->json([
            'score'   => $score,
            'passed'  => $passed,
            'total'   => $total,
            'correct' => $correct,
        ]);
    }

    private function requireMembership(Request $request, Community $community): void
    {
        $user = $request->user();

        if ($community->owner_id === $user->id) {
            return;
        }

        if ($community->isFree()) {
            abort_unless(
                CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists(),
                403,
                'You must be a member of this community.'
            );
            return;
        }

        abort_unless(
            Subscription::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists(),
            403,
            'An active membership is required.'
        );
    }
}
