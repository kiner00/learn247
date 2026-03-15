<?php

namespace App\Actions\Classroom;

use App\Models\Comment;
use App\Models\CourseLesson;
use App\Models\User;

class CreateLessonComment
{
    public function execute(User $user, CourseLesson $lesson, int $communityId, string $content): Comment
    {
        return Comment::create([
            'lesson_id'    => $lesson->id,
            'community_id' => $communityId,
            'user_id'      => $user->id,
            'content'      => $content,
        ]);
    }
}
