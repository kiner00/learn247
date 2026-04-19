<?php

namespace App\Ai\Tools;

use App\Models\CourseEnrollment;
use App\Models\LessonCompletion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetEnrolledCoursesTool implements Tool
{
    public function __construct(private int $userId) {}

    public function description(): string
    {
        return 'Get all courses the user has individually enrolled in (paid one-time courses), including their progress in each course.';
    }

    public function handle(Request $request): string
    {
        $enrollments = CourseEnrollment::where('user_id', $this->userId)
            ->where('status', CourseEnrollment::STATUS_PAID)
            ->with('course.community:id,name', 'course.modules.lessons')
            ->get();

        if ($enrollments->isEmpty()) {
            return 'The user has not enrolled in any paid courses.';
        }

        $completedIds = LessonCompletion::where('user_id', $this->userId)->pluck('lesson_id')->all();

        $result = $enrollments->map(function ($enrollment) use ($completedIds) {
            $course = $enrollment->course;
            $allLessons = $course->modules->flatMap->lessons;
            $done = $allLessons->whereIn('id', $completedIds)->count();

            return [
                'course' => $course->title,
                'community' => $course->community->name,
                'enrolled_at' => $enrollment->paid_at?->toDateString(),
                'expires_at' => $enrollment->expires_at?->toDateString(),
                'lessons_done' => $done,
                'lessons_total' => $allLessons->count(),
            ];
        })->values()->toArray();

        return json_encode($result, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
