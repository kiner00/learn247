<?php

namespace App\Queries\Classroom;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\LessonCompletion;
use App\Models\QuizAttempt;
use Illuminate\Support\Collection;

class GetCourseDetail
{
    /**
     * @return array{completed_ids: array, progress: int, quiz_attempts: Collection, certificate: Certificate|null}
     */
    public function execute(Course $course, ?int $userId, bool $hasAccess = false): array
    {
        $course->load('modules.lessons.quiz.questions.options');

        $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));

        if (! $userId || ! $hasAccess) {
            return [
                'completed_ids' => [],
                'progress'      => 0,
                'quiz_attempts' => collect(),
                'certificate'   => null,
            ];
        }

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
            ->map(fn ($attempts) => $attempts->sortByDesc('score')->first());

        $certificate = Certificate::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->first();

        return [
            'completed_ids' => $completedIds,
            'progress'      => $progress,
            'quiz_attempts' => $quizAttempts,
            'certificate'   => $certificate,
        ];
    }
}
