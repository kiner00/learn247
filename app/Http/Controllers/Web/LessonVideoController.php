<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\ManageLesson;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Services\Classroom\CourseAccessService;
use App\Services\Community\PlanLimitService;
use App\Services\HlsManifestRewriter;
use App\Services\S3MultipartUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonVideoController extends Controller
{
    public function uploadLessonVideo(Request $request, Community $community, ManageLesson $action, PlanLimitService $planLimit): JsonResponse
    {
        try {
            $this->authorize('manage', $community);

            $owner = $community->owner;
            if (! $planLimit->canUploadVideo($owner)) {
                return response()->json(['error' => 'Video uploads require a Pro plan.'], 403);
            }

            $request->validate([
                'filename' => ['required', 'string', 'max:255'],
                'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm,video/x-msvideo'],
                'size' => ['required', 'integer', 'min:1'],
            ]);

            $maxBytes = $planLimit->maxVideoSizeMb($owner->creatorPlan()) * 1024 * 1024;

            if ($request->size > $maxBytes) {
                return response()->json([
                    'error' => 'File too large. Maximum size is '.$planLimit->maxVideoSizeMb($owner->creatorPlan()).'MB.',
                ], 422);
            }

            $extension = pathinfo($request->filename, PATHINFO_EXTENSION) ?: 'mp4';
            $key = 'lesson-videos/'.Str::uuid().'.'.$extension;

            $client = Storage::disk('s3')->getClient();
            $command = $client->getCommand('PutObject', [
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Key' => $key,
                'ContentType' => $request->content_type,
            ]);

            $presigned = $client->createPresignedRequest($command, '+30 minutes');

            return response()->json([
                'upload_url' => (string) $presigned->getUri(),
                'key' => $key,
            ]);
        } catch (\Throwable $e) {
            Log::error('LessonVideoController@uploadLessonVideo failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function uploadPreviewVideo(Request $request, Community $community, PlanLimitService $planLimit): JsonResponse
    {
        try {
            $this->authorize('manage', $community);

            $owner = $community->owner;
            if (! $planLimit->canUploadVideo($owner)) {
                return response()->json(['error' => 'Preview video uploads require a Pro plan.'], 403);
            }

            $request->validate([
                'filename' => ['required', 'string', 'max:255'],
                'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm'],
                'size' => ['required', 'integer', 'min:1'],
            ]);

            $maxBytes = $planLimit->maxVideoSizeMb($owner->creatorPlan()) * 1024 * 1024;

            if ($request->size > $maxBytes) {
                return response()->json([
                    'error' => 'File too large. Maximum size is '.$planLimit->maxVideoSizeMb($owner->creatorPlan()).'MB.',
                ], 422);
            }

            $extension = pathinfo($request->filename, PATHINFO_EXTENSION) ?: 'mp4';
            $key = 'course-previews/'.Str::uuid().'.'.$extension;

            $client = Storage::disk('s3')->getClient();
            $command = $client->getCommand('PutObject', [
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Key' => $key,
                'ContentType' => $request->content_type,
            ]);

            $presigned = $client->createPresignedRequest($command, '+30 minutes');

            return response()->json([
                'upload_url' => (string) $presigned->getUri(),
                'key' => $key,
            ]);
        } catch (\Throwable $e) {
            Log::error('LessonVideoController@uploadPreviewVideo failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function initiateMultipartUpload(Request $request, Community $community, PlanLimitService $planLimit, S3MultipartUploadService $multipart): JsonResponse
    {
        $this->authorize('manage', $community);

        $owner = $community->owner;
        if (! $planLimit->canUploadVideo($owner)) {
            return response()->json(['error' => 'Video uploads require a Pro plan.'], 403);
        }

        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm,video/x-msvideo'],
            'size' => ['required', 'integer', 'min:1'],
            'type' => ['sometimes', 'string', 'in:lesson,preview'],
        ]);

        $maxBytes = $planLimit->maxVideoSizeMb($owner->creatorPlan()) * 1024 * 1024;
        if ($request->size > $maxBytes) {
            return response()->json([
                'error' => 'File too large. Maximum size is '.$planLimit->maxVideoSizeMb($owner->creatorPlan()).'MB.',
            ], 422);
        }

        $prefix = $request->input('type') === 'preview' ? 'course-previews' : 'lesson-videos';

        return response()->json($multipart->initiate($request->filename, $request->content_type, $prefix));
    }

    public function getPartUploadUrl(Request $request, Community $community, S3MultipartUploadService $multipart): JsonResponse
    {
        $this->authorize('manage', $community);

        $request->validate([
            'key' => ['required', 'string'],
            'upload_id' => ['required', 'string'],
            'part_number' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        return response()->json([
            'url' => $multipart->partUrl($request->key, $request->upload_id, $request->part_number),
        ]);
    }

    public function completeMultipartUpload(Request $request, Community $community, S3MultipartUploadService $multipart): JsonResponse
    {
        $this->authorize('manage', $community);

        $request->validate([
            'key' => ['required', 'string'],
            'upload_id' => ['required', 'string'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.PartNumber' => ['required', 'integer'],
            'parts.*.ETag' => ['required', 'string'],
        ]);

        $multipart->complete($request->key, $request->upload_id, $request->parts);

        return response()->json([
            'key' => $request->key,
            'url' => Storage::disk('s3')->url($request->key),
        ]);
    }

    public function abortMultipartUpload(Request $request, Community $community, S3MultipartUploadService $multipart): JsonResponse
    {
        $this->authorize('manage', $community);

        $request->validate([
            'key' => ['required', 'string'],
            'upload_id' => ['required', 'string'],
        ]);

        $multipart->abort($request->key, $request->upload_id);

        return response()->json(['ok' => true]);
    }

    public function stream(Request $request, Community $community, Course $course, CourseLesson $lesson, CourseAccessService $access): JsonResponse
    {
        try {
            $user = $request->user();
            $canManage = $user?->can('manage', $community) ?? false;

            if (! $course->is_published && ! $canManage) {
                abort(404);
            }

            if (! $canManage && ! $access->hasAccess($user, $community, $course)) {
                abort(403, 'You do not have access to this course.');
            }

            if (! $lesson->video_path) {
                abort(404, 'No video for this lesson.');
            }

            if ($lesson->video_hls_path && $lesson->video_transcode_status === 'completed') {
                $proxyBase = route('communities.classroom.lessons.hls', [
                    'community' => $community,
                    'course' => $course,
                    'lesson' => $lesson,
                    'file' => basename($lesson->video_hls_path),
                ]);

                return response()->json([
                    'url' => $proxyBase,
                    'type' => 'hls',
                    'transcode_status' => $lesson->video_transcode_status,
                ]);
            }

            $path = $lesson->video_path;

            if (str_starts_with($path, 'http')) {
                $parsed = parse_url($path);
                $path = ltrim($parsed['path'] ?? '', '/');
            }

            $url = Storage::temporaryUrl($path, now()->addHours(2));

            return response()->json([
                'url' => $url,
                'type' => 'raw',
                'transcode_status' => $lesson->video_transcode_status,
            ]);
        } catch (\Throwable $e) {
            Log::error('LessonVideoController@stream failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function hlsFile(Request $request, Community $community, Course $course, CourseLesson $lesson, string $file, CourseAccessService $access, HlsManifestRewriter $hls)
    {
        $user = $request->user();
        $canManage = $user?->can('manage', $community) ?? false;

        if (! $course->is_published && ! $canManage) {
            abort(404);
        }

        if (! $canManage && ! $access->hasAccess($user, $community, $course)) {
            abort(403);
        }

        if (! $lesson->video_hls_path || $lesson->video_transcode_status !== 'completed') {
            abort(404);
        }

        return $hls->serve(
            dirname($lesson->video_hls_path),
            $file,
            fn (string $relative) => route('communities.classroom.lessons.hls', [
                'community' => $community,
                'course' => $course,
                'lesson' => $lesson,
                'file' => $relative,
            ]),
        );
    }

    public function transcodeStatus(Request $request, Community $community, Course $course, CourseLesson $lesson): JsonResponse
    {
        return response()->json([
            'status' => $lesson->video_transcode_status,
            'percent' => $lesson->video_transcode_percent,
        ]);
    }

    public function trackPlay(Request $request, Community $community, Course $course, CourseLesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:1', 'max:36000'],
        ]);

        $lesson->increment('video_play_count');
        $lesson->increment('video_watch_seconds', $data['seconds']);

        return response()->json(['ok' => true]);
    }
}
