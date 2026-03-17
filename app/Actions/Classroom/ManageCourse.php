<?php

namespace App\Actions\Classroom;

use App\Models\Community;
use App\Models\Course;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

    public function reorder(Community $community, array $courseIds): void
    {
        foreach ($courseIds as $position => $courseId) {
            $community->courses()->where('id', $courseId)->update(['position' => $position]);
        }
    }

    public function destroy(Course $course): void
    {
        if ($course->cover_image) {
            $path = str_replace(asset('storage/'), '', $course->cover_image);
            Storage::disk('public')->delete($path);
        }

        $course->delete();
    }
}
