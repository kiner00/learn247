<?php

namespace App\Queries\Classroom;

use App\Models\Certificate;
use App\Models\CertificationAttempt;
use App\Models\Comment;
use App\Models\Course;
use App\Models\CourseEnrollment;
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
        $course->load('modules.lessons.quiz.questions.options', 'certification.questions.options');

        $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));

        $certificationExam = $course->certification;

        if (! $userId || ! $hasAccess) {
            $lessonComments = Comment::whereIn('lesson_id', $lessonIds)
                ->whereNull('parent_id')
                ->with(['author:id,name,username,avatar', 'replies.author:id,name,username,avatar'])
                ->latest()
                ->get()
                ->groupBy('lesson_id')
                ->map(fn ($comments) => $comments->values());

            return [
                'completed_ids'     => [],
                'progress'          => 0,
                'quiz_attempts'     => collect(),
                'certificate'       => null,
                'lesson_comments'   => $lessonComments,
                'enrollment'        => null,
                'certification_exam' => $certificationExam,
                'cert_attempt'      => null,
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

        $certAttempt = $certificationExam
            ? CertificationAttempt::where('user_id', $userId)
                ->where('certification_id', $certificationExam->id)
                ->orderByDesc('score')
                ->first()
            : null;

        $lessonComments = Comment::whereIn('lesson_id', $lessonIds)
            ->whereNull('parent_id')
            ->with(['author:id,name,username,avatar', 'replies.author:id,name,username,avatar'])
            ->latest()
            ->get()
            ->groupBy('lesson_id')
            ->map(fn ($comments) => $comments->values());

        $enrollment = $userId
            ? CourseEnrollment::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->orderByDesc('id')
                ->first()
            : null;

        return [
            'completed_ids'      => $completedIds,
            'progress'           => $progress,
            'quiz_attempts'      => $quizAttempts,
            'certificate'        => $certificate,
            'lesson_comments'    => $lessonComments,
            'enrollment'         => $enrollment ? ['status' => $enrollment->status] : null,
            'certification_exam' => $certificationExam,
            'cert_attempt'       => $certAttempt,
        ];
    }
}
