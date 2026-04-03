<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\CompleteLesson;
use App\Actions\Classroom\ManageCourse;
use App\Actions\Classroom\ManageLesson;
use App\Actions\Classroom\ManageModule;
use App\Http\Controllers\Controller;
use App\Services\Classroom\CourseAccessService;
use App\Services\Community\PlanLimitService;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Queries\Classroom\GetCourseDetail;
use App\Queries\Classroom\GetCourseList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Community $community, GetCourseList $query): Response
    {
        try {
            $userId        = auth()->id();
            $isSuperAdmin  = auth()->user()?->isSuperAdmin() ?? false;
            $community->loadCount('members');
            $courses    = $query->execute($community, $userId, $isSuperAdmin);
            $affiliate  = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;
            $membership = $userId ? CommunityMember::where('community_id', $community->id)->where('user_id', $userId)->first(['id', 'membership_type']) : null;
            $canManage  = auth()->user()?->can('manage', $community) ?? false;

            $ownerPlan = $community->owner?->creatorPlan() ?? 'free';

            return Inertia::render('Communities/Classroom/Index', compact('community', 'courses', 'affiliate', 'membership', 'canManage', 'ownerPlan'));
        } catch (\Throwable $e) {
            Log::error('ClassroomController@index failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function storeCourse(Request $request, Community $community, ManageCourse $action, PlanLimitService $planLimit): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            if (! $planLimit->canCreateCourse($community->owner, $community)) {
                return back()->withErrors([
                    'plan' => 'Free creators can only have 3 courses per community. Upgrade to Basic or Pro for more courses.',
                ]);
            }

            $data = $request->validate([
                'title'         => ['required', 'string', 'max:255'],
                'description'   => ['nullable', 'string', 'max:2000'],
                'cover_image'   => ['nullable', 'image', 'max:10240'],
                'preview_video' => ['nullable', 'string', 'max:1000'],
                'access_type'   => ['required', 'in:free,inclusive,paid_once,paid_monthly,member_once'],
                'price'         => ['nullable', 'numeric', 'min:0', 'required_if:access_type,paid_once', 'required_if:access_type,paid_monthly'],
            ]);

            $action->store($community, $data, $request->file('cover_image'));

            return back()->with('success', 'Course created!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@storeCourse failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function updateCourse(Request $request, Community $community, Course $course, ManageCourse $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $data = $request->validate([
                'title'                => ['required', 'string', 'max:255'],
                'description'          => ['nullable', 'string', 'max:2000'],
                'cover_image'          => ['nullable', 'image', 'max:10240'],
                'preview_video'        => ['nullable', 'string', 'max:1000'],
                'remove_preview_video' => ['nullable', 'boolean'],
                'access_type'          => ['required', 'in:free,inclusive,paid_once,paid_monthly,member_once'],
                'price'                => ['nullable', 'numeric', 'min:0', 'required_if:access_type,paid_once', 'required_if:access_type,paid_monthly'],
            ]);

            $action->update($course, $data, $request->file('cover_image'));

            return back()->with('success', 'Course updated!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@updateCourse failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function reorderCourses(Request $request, Community $community, ManageCourse $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $request->validate([
                'course_ids'   => ['required', 'array'],
                'course_ids.*' => ['required', 'integer', 'exists:courses,id'],
            ]);

            $action->reorder($community, $request->course_ids);

            return back()->with('success', 'Courses reordered!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@reorderCourses failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function destroyCourse(Request $request, Community $community, Course $course, ManageCourse $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $action->destroy($course);

            return redirect()->route('communities.classroom', $community)->with('success', 'Course deleted!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@destroyCourse failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function togglePublish(Request $request, Community $community, Course $course): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $course->update(['is_published' => ! $course->is_published]);

            $label = $course->is_published ? 'published' : 'set to draft';

            return back()->with('success', "Course {$label}!");
        } catch (\Throwable $e) {
            Log::error('ClassroomController@togglePublish failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function showCourse(Community $community, Course $course, GetCourseDetail $query, CourseAccessService $access): Response
    {
        try {
            $userId    = auth()->id();
            $canManage = auth()->user()?->can('manage', $community) ?? false;

            // Unpublished courses are only visible to managers (owner/admin)
            if (! $course->is_published && ! $canManage) {
                abort(404);
            }

            $hasAccess = $access->hasAccess(auth()->user(), $community, $course);
            $detail    = $query->execute($course, $userId, $hasAccess);

            return Inertia::render('Communities/Classroom/Show', [
                'community'      => $community,
                'course'         => $course->append([]),
                'hasAccess'      => $hasAccess,
                'enrollment'     => $detail['enrollment'],
                'completedIds'   => $detail['completed_ids'],
                'progress'       => $detail['progress'],
                'lessonComments' => $detail['lesson_comments'],
                'quizAttempts'   => $detail['quiz_attempts'],
                'canManage'      => $canManage,
                'canUploadVideo' => $canManage && ($community->owner?->creatorPlan() === 'pro'),
            ]);
        } catch (\Throwable $e) {
            Log::error('ClassroomController@showCourse failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function storeModule(Request $request, Community $community, Course $course, ManageModule $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);
            $data = $request->validate([
                'title'   => ['required', 'string', 'max:255'],
                'is_free' => ['sometimes', 'boolean'],
            ]);
            $action->store($course, $data);

            return back()->with('success', 'Module added!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@storeModule failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function updateModule(Request $request, Community $community, Course $course, CourseModule $module, ManageModule $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);
            $data = $request->validate([
                'title'   => ['required', 'string', 'max:255'],
                'is_free' => ['sometimes', 'boolean'],
            ]);
            $action->update($module, $data);

            return back()->with('success', 'Module updated!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@updateModule failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function destroyModule(Request $request, Community $community, Course $course, CourseModule $module, ManageModule $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $action->destroy($module);

            return back()->with('success', 'Module deleted!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@destroyModule failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function destroyLesson(Request $request, Community $community, Course $course, CourseModule $module, CourseLesson $lesson): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $lesson->delete();

            return back()->with('success', 'Lesson deleted!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@destroyLesson failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function storeLesson(Request $request, Community $community, Course $course, CourseModule $module, ManageLesson $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $data = $request->validate([
                'title'      => ['required', 'string', 'max:255'],
                'content'    => ['nullable', 'string'],
                'embed_html' => ['nullable', 'string'],
                'video_url'  => ['nullable', 'url', 'max:500'],
                'cta_label'  => ['nullable', 'string', 'max:100'],
                'cta_url'    => ['nullable', 'url', 'max:500'],
            ]);

            $action->store($module, $data);

            return back()->with('success', 'Lesson added!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@storeLesson failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function completeLesson(Request $request, Community $community, Course $course, CourseLesson $lesson, CompleteLesson $action): RedirectResponse
    {
        try {
            $action->execute($request->user(), $lesson, $community->id);

            return back()->with('success', 'Lesson marked as complete!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@completeLesson failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function updateLesson(Request $request, Community $community, Course $course, CourseModule $module, CourseLesson $lesson, ManageLesson $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $data = $request->validate([
                'title'      => ['sometimes', 'string', 'max:255'],
                'content'    => ['nullable', 'string'],
                'embed_html' => ['nullable', 'string'],
                'video_url'  => ['nullable', 'url', 'max:500'],
                'video_path' => ['nullable', 'string', 'max:1000'],
                'cta_label'  => ['nullable', 'string', 'max:100'],
                'cta_url'    => ['nullable', 'url', 'max:500'],
            ]);

            $action->update($lesson, $data);

            return back()->with('success', 'Lesson updated!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@updateLesson failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function uploadLessonImage(Request $request, Community $community, ManageLesson $action): JsonResponse
    {
        try {
            $this->authorize('manage', $community);

            $request->validate([
                'image' => ['required', 'image', 'max:10240'],
            ]);

            $url = $action->uploadImage($request->file('image'));

            return response()->json(['url' => $url]);
        } catch (\Throwable $e) {
            Log::error('ClassroomController@uploadLessonImage failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function uploadLessonVideo(Request $request, Community $community, ManageLesson $action, PlanLimitService $planLimit): JsonResponse
    {
        try {
            $this->authorize('manage', $community);

            $owner = $community->owner;
            if (! $planLimit->canUploadVideo($owner)) {
                return response()->json(['error' => 'Video uploads require a Pro plan.'], 403);
            }

            $request->validate([
                'filename'     => ['required', 'string', 'max:255'],
                'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm,video/x-msvideo'],
                'size'         => ['required', 'integer', 'min:1'],
            ]);

            $maxBytes = $planLimit->maxVideoSizeMb($owner->creatorPlan()) * 1024 * 1024;

            if ($request->size > $maxBytes) {
                return response()->json([
                    'error' => 'File too large. Maximum size is ' . $planLimit->maxVideoSizeMb($owner->creatorPlan()) . 'MB.',
                ], 422);
            }

            $extension = pathinfo($request->filename, PATHINFO_EXTENSION) ?: 'mp4';
            $key       = 'lesson-videos/' . \Illuminate\Support\Str::uuid() . '.' . $extension;

            // Generate a presigned PUT URL (expires in 30 minutes)
            $client  = Storage::disk('s3')->getClient();
            $command = $client->getCommand('PutObject', [
                'Bucket'      => config('filesystems.disks.s3.bucket'),
                'Key'         => $key,
                'ContentType' => $request->content_type,
            ]);

            $presigned = $client->createPresignedRequest($command, '+30 minutes');

            return response()->json([
                'upload_url' => (string) $presigned->getUri(),
                'key'        => $key,
            ]);
        } catch (\Throwable $e) {
            Log::error('ClassroomController@uploadLessonVideo failed', ['error' => $e->getMessage(), 'community' => $community->id]);
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
                'filename'     => ['required', 'string', 'max:255'],
                'content_type' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm'],
                'size'         => ['required', 'integer', 'min:1'],
            ]);

            $maxBytes = $planLimit->maxVideoSizeMb($owner->creatorPlan()) * 1024 * 1024;

            if ($request->size > $maxBytes) {
                return response()->json([
                    'error' => 'File too large. Maximum size is ' . $planLimit->maxVideoSizeMb($owner->creatorPlan()) . 'MB.',
                ], 422);
            }

            $extension = pathinfo($request->filename, PATHINFO_EXTENSION) ?: 'mp4';
            $key       = 'course-previews/' . \Illuminate\Support\Str::uuid() . '.' . $extension;

            $client  = Storage::disk('s3')->getClient();
            $command = $client->getCommand('PutObject', [
                'Bucket'      => config('filesystems.disks.s3.bucket'),
                'Key'         => $key,
                'ContentType' => $request->content_type,
            ]);

            $presigned = $client->createPresignedRequest($command, '+30 minutes');

            return response()->json([
                'upload_url' => (string) $presigned->getUri(),
                'key'        => $key,
            ]);
        } catch (\Throwable $e) {
            Log::error('ClassroomController@uploadPreviewVideo failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function streamLessonVideo(Request $request, Community $community, Course $course, CourseLesson $lesson, CourseAccessService $access): JsonResponse
    {
        try {
            $user      = $request->user();
            $canManage = $user?->can('manage', $community) ?? false;

            // Unpublished courses only visible to managers
            if (! $course->is_published && ! $canManage) {
                abort(404);
            }

            // Check course-level access
            if (! $canManage && ! $access->hasAccess($user, $community, $course)) {
                abort(403, 'You do not have access to this course.');
            }

            if (! $lesson->video_path) {
                abort(404, 'No video for this lesson.');
            }

            // Prefer HLS if transcoding is complete
            if ($lesson->video_hls_path && $lesson->video_transcode_status === 'completed') {
                // Return the proxy base URL — HLS.js will fetch playlist/segments through our proxy
                $proxyBase = route('communities.classroom.lessons.hls', [
                    'community' => $community,
                    'course'    => $course,
                    'lesson'    => $lesson,
                    'file'      => 'master.m3u8',
                ]);

                return response()->json([
                    'url'              => $proxyBase,
                    'type'             => 'hls',
                    'transcode_status' => $lesson->video_transcode_status,
                ]);
            }

            // Fallback to raw video
            $path = $lesson->video_path;

            // Handle legacy full S3 URLs stored before the migration to private keys
            if (str_starts_with($path, 'http')) {
                $parsed = parse_url($path);
                $path   = ltrim($parsed['path'] ?? '', '/');
            }

            // Generate a temporary signed URL (expires in 2 hours)
            $url = Storage::temporaryUrl($path, now()->addHours(2));

            return response()->json([
                'url'  => $url,
                'type' => 'raw',
                'transcode_status' => $lesson->video_transcode_status,
            ]);
        } catch (\Throwable $e) {
            Log::error('ClassroomController@streamLessonVideo failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

    public function hlsFile(Request $request, Community $community, Course $course, CourseLesson $lesson, string $file, CourseAccessService $access)
    {
        $user      = $request->user();
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

        // Only allow .m3u8 and .ts files
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (! in_array($ext, ['m3u8', 'ts'])) {
            abort(400, 'Invalid HLS file type.');
        }

        $hlsPrefix = dirname($lesson->video_hls_path);
        $s3Key     = $hlsPrefix . '/' . $file;

        // Prevent path traversal
        if (! str_starts_with(realpath(dirname($s3Key) . '/') ?: $s3Key, $hlsPrefix)) {
            abort(403);
        }

        if (! Storage::exists($s3Key)) {
            abort(404);
        }

        $contentType = match ($ext) {
            'm3u8'  => 'application/vnd.apple.mpegurl',
            'ts'    => 'video/MP2T',
            default => 'application/octet-stream',
        };

        // For .m3u8 playlists, rewrite relative paths to go through our proxy
        if ($ext === 'm3u8') {
            $content  = Storage::get($s3Key);
            $proxyDir = dirname($file);
            $prefix   = $proxyDir !== '.' ? $proxyDir . '/' : '';

            // Rewrite relative .ts and .m3u8 references to proxy URLs
            $content = preg_replace_callback(
                '/^(?!#)(.+\.(ts|m3u8))$/m',
                function ($matches) use ($community, $course, $lesson, $prefix) {
                    $refFile = $prefix . $matches[1];
                    return route('communities.classroom.lessons.hls', [
                        'community' => $community,
                        'course'    => $course,
                        'lesson'    => $lesson,
                        'file'      => $refFile,
                    ]);
                },
                $content
            );

            return response($content, 200, [
                'Content-Type'  => $contentType,
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        // For .ts segments, redirect to a signed S3 URL
        $url = Storage::temporaryUrl($s3Key, now()->addHours(2));

        return redirect($url);
    }

    public function transcodeStatus(Request $request, Community $community, Course $course, CourseLesson $lesson): JsonResponse
    {
        return response()->json([
            'status'  => $lesson->video_transcode_status,
            'percent' => $lesson->video_transcode_percent,
        ]);
    }

    public function reorderLessons(Request $request, Community $community, Course $course, CourseModule $module, ManageLesson $action): RedirectResponse
    {
        try {
            $this->authorize('manage', $community);

            $request->validate([
                'lesson_ids'   => ['required', 'array'],
                'lesson_ids.*' => ['required', 'integer', 'exists:course_lessons,id'],
            ]);

            $action->reorder($module, $request->lesson_ids);

            return back()->with('success', 'Lessons reordered!');
        } catch (\Throwable $e) {
            Log::error('ClassroomController@reorderLessons failed', ['error' => $e->getMessage(), 'community' => $community->id]);
            throw $e;
        }
    }

}
