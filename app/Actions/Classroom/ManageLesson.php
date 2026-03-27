<?php

namespace App\Actions\Classroom;

use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Services\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ManageLesson
{
    public function __construct(private StorageService $storage) {}

    public function store(CourseModule $module, array $data): CourseLesson
    {
        $position = $module->lessons()->max('position') + 1;

        return $module->lessons()->create(array_merge($data, ['position' => $position]));
    }

    public function update(CourseLesson $lesson, array $data): CourseLesson
    {
        // When setting a video URL, clear the uploaded video
        if (!empty($data['video_url']) && $lesson->video_path) {
            $this->storage->delete($lesson->video_path);
            $data['video_path'] = null;
        }

        // When uploading a new video, clear old uploaded video and external URL
        if (!empty($data['video_path']) && $lesson->video_path && $data['video_path'] !== $lesson->video_path) {
            $this->storage->delete($lesson->video_path);
        }

        $lesson->update($data);

        return $lesson;
    }

    public function uploadImage(UploadedFile $image): string
    {
        return $this->storage->upload($image, 'lesson-images');
    }

    public function uploadVideo(UploadedFile $video): string
    {
        $path = $video->store('lesson-videos', config('filesystems.default'));

        // Store as private so subscribers cannot download directly
        Storage::setVisibility($path, 'private');

        // Return the S3 key (not the public URL) — videos are served via signed URLs
        return $path;
    }

    public function reorder(CourseModule $module, array $lessonIds): void
    {
        foreach ($lessonIds as $position => $lessonId) {
            $module->lessons()->where('id', $lessonId)->update(['position' => $position]);
        }
    }
}
