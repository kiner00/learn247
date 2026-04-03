<?php

namespace App\Actions\Classroom;

use App\Models\Community;
use App\Models\Course;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ManageCourse
{
    public function __construct(private StorageService $storage) {}

    public function store(Community $community, array $data, ?UploadedFile $coverImage = null): Course
    {
        if ($coverImage) {
            $data['cover_image'] = $this->storage->upload($coverImage, 'course-covers');
        }

        // preview_video arrives as an S3 key string (uploaded via presigned URL)
        // Convert to full URL for storage
        if (! empty($data['preview_video'])) {
            $data['preview_video'] = Storage::url($data['preview_video']);
        }

        $position = $community->courses()->max('position') + 1;

        return $community->courses()->create(array_merge($data, ['position' => $position]));
    }

    public function update(Course $course, array $data, ?UploadedFile $coverImage = null): Course
    {
        if ($coverImage) {
            $this->storage->delete($course->cover_image);
            $data['cover_image'] = $this->storage->upload($coverImage, 'course-covers');
        } else {
            unset($data['cover_image']);
        }

        // preview_video arrives as an S3 key string (uploaded via presigned URL)
        if (! empty($data['preview_video'])) {
            $this->deletePreviewVideo($course);
            $data['preview_video'] = Storage::url($data['preview_video']);
        } elseif (! empty($data['remove_preview_video'])) {
            $this->deletePreviewVideo($course);
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
        $this->deletePreviewVideo($course);
        $course->delete();
    }

    private function deletePreviewVideo(Course $course): void
    {
        if (! $course->preview_video) {
            return;
        }

        // preview_video is stored as a full URL; extract S3 key for deletion
        $url = $course->preview_video;
        if (str_contains($url, 'course-previews/')) {
            $key = substr($url, strpos($url, 'course-previews/'));
            Storage::delete($key);
        } else {
            $this->storage->delete($url);
        }
    }
}
