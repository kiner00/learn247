<?php

namespace App\Actions\Classroom;

use App\Models\CourseLesson;
use App\Models\LessonCompletion;
use App\Models\User;
use App\Services\BadgeService;

class CompleteLesson
{
    public function execute(User $user, CourseLesson $lesson, ?int $communityId = null): LessonCompletion
    {
        $completion = LessonCompletion::firstOrCreate([
            'user_id'   => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        app(BadgeService::class)->evaluate($user, $communityId);

        return $completion;
    }
}
