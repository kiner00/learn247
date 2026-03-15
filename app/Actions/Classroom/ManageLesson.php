<?php

namespace App\Actions\Classroom;

use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Support\Facades\Storage;

class ManageLesson
{
    public function store(CourseModule $module, array $data): CourseLesson
    {
        $position = $module->lessons()->max('position') + 1;

        return $module->lessons()->create(array_merge($data, ['position' => $position]));
    }

    public function update(CourseLesson $lesson, array $data): CourseLesson
    {
        if (!empty($data['video_url']) && $lesson->video_path) {
            Storage::disk('public')->delete($lesson->video_path);
            $data['video_path'] = null;
        }

        $lesson->update($data);

        return $lesson;
    }
}
