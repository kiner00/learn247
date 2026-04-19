<?php

namespace App\Actions\Classroom;

use App\Contracts\BadgeEvaluator;
use App\Models\CourseLesson;
use App\Models\LessonCompletion;
use App\Models\User;

class CompleteLesson
{
    public function __construct(private BadgeEvaluator $badges) {}

    public function execute(User $user, CourseLesson $lesson, ?int $communityId = null): LessonCompletion
    {
        $completion = LessonCompletion::firstOrCreate([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        $this->badges->evaluate($user, $communityId);

        return $completion;
    }
}
