<?php

namespace App\Queries\Classroom;

use App\Models\Community;
use App\Models\LessonCompletion;
use Illuminate\Support\Collection;

class GetCourseList
{
    public function execute(Community $community, int $userId): Collection
    {
        return $community->courses()->with('modules.lessons')->get()->map(function ($course) use ($userId) {
            $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
            $total     = $lessonIds->count();
            $completed = $total > 0
                ? LessonCompletion::where('user_id', $userId)->whereIn('lesson_id', $lessonIds)->count()
                : 0;

            return [
                'id'          => $course->id,
                'title'       => $course->title,
                'description' => $course->description,
                'cover_image' => $course->cover_image,
                'position'    => $course->position,
                'total'       => $total,
                'completed'   => $completed,
                'progress'    => $total > 0 ? round($completed / $total * 100) : 0,
            ];
        });
    }
}
