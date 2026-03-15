<?php

namespace App\Actions\Classroom;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\LessonCompletion;
use App\Models\User;

class IssueCertificate
{
    public function execute(User $user, Course $course): Certificate
    {
        $course->load('modules.lessons');

        $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
        $total     = $lessonIds->count();

        abort_if($total === 0, 422, 'This course has no lessons.');

        $completed = LessonCompletion::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->count();

        abort_unless($completed >= $total, 422, 'You have not completed all lessons yet.');

        return Certificate::firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['issued_at' => now()]
        );
    }
}
