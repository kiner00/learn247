<?php

namespace App\Actions\Classroom;

use App\Models\Community;
use App\Models\Course;
use Illuminate\Http\UploadedFile;

class ManageCourse
{
    public function store(Community $community, array $data, ?UploadedFile $coverImage = null): Course
    {
        if ($coverImage) {
            $data['cover_image'] = asset('storage/' . $coverImage->store('course-covers', 'public'));
        }

        $position = $community->courses()->max('position') + 1;

        return $community->courses()->create(array_merge($data, ['position' => $position]));
    }

    public function update(Course $course, array $data, ?UploadedFile $coverImage = null): Course
    {
        if ($coverImage) {
            $data['cover_image'] = asset('storage/' . $coverImage->store('course-covers', 'public'));
        } else {
            unset($data['cover_image']);
        }

        $course->update($data);

        return $course;
    }
}
