<?php

namespace App\Actions\Classroom;

use App\Models\Community;
use App\Models\Course;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;

class ManageCourse
{
    public function __construct(private StorageService $storage) {}

    public function store(Community $community, array $data, ?UploadedFile $coverImage = null, ?UploadedFile $previewVideo = null): Course
    {
        if ($coverImage) {
            $data['cover_image'] = $this->storage->upload($coverImage, 'course-covers');
        }

        if ($previewVideo) {
            $data['preview_video'] = $this->storage->upload($previewVideo, 'course-previews');
        }

        $position = $community->courses()->max('position') + 1;

        return $community->courses()->create(array_merge($data, ['position' => $position]));
    }

    public function update(Course $course, array $data, ?UploadedFile $coverImage = null, ?UploadedFile $previewVideo = null): Course
    {
        if ($coverImage) {
            $this->storage->delete($course->cover_image);
            $data['cover_image'] = $this->storage->upload($coverImage, 'course-covers');
        } else {
            unset($data['cover_image']);
        }

        if ($previewVideo) {
            $this->storage->delete($course->preview_video);
            $data['preview_video'] = $this->storage->upload($previewVideo, 'course-previews');
        } elseif (! empty($data['remove_preview_video'])) {
            $this->storage->delete($course->preview_video);
            $data['preview_video'] = null;
        } else {
            unset($data['preview_video']);
        }

        unset($data['remove_preview_video']);

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
        $this->storage->delete($course->cover_image);
        $this->storage->delete($course->preview_video);
        $course->delete();
    }
}
