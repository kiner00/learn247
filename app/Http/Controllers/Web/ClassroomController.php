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
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    private function canManage(?\Illuminate\Contracts\Auth\Authenticatable $user, Community $community): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->id === $community->owner_id || $user->isSuperAdmin()) {
            return true;
        }

        return CommunityMember::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('role', CommunityMember::ROLE_ADMIN)
            ->exists();
    }

    public function index(Community $community, GetCourseList $query): Response
    {
        $userId        = auth()->id();
        $isSuperAdmin  = auth()->user()?->isSuperAdmin() ?? false;
        $community->loadCount('members');
        $courses    = $query->execute($community, $userId, $isSuperAdmin);
        $affiliate  = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;
        $membership = $userId ? CommunityMember::where('community_id', $community->id)->where('user_id', $userId)->first(['id', 'membership_type']) : null;
        $canManage  = $this->canManage(auth()->user(), $community);

        $ownerPlan = $community->owner?->creatorPlan() ?? 'free';

        return Inertia::render('Communities/Classroom/Index', compact('community', 'courses', 'affiliate', 'membership', 'canManage', 'ownerPlan'));
    }

    public function storeCourse(Request $request, Community $community, ManageCourse $action, PlanLimitService $planLimit): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

        if (! $planLimit->canCreateCourse($community->owner, $community)) {
            return back()->withErrors([
                'plan' => 'Free creators can only have 3 courses per community. Upgrade to Basic or Pro for more courses.',
            ]);
        }

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
            'access_type' => ['required', 'in:free,inclusive,paid_once,paid_monthly,member_once'],
            'price'       => ['nullable', 'numeric', 'min:0', 'required_if:access_type,paid_once', 'required_if:access_type,paid_monthly'],
        ]);

        $action->store($community, $data, $request->file('cover_image'));

        return back()->with('success', 'Course created!');
    }

    public function updateCourse(Request $request, Community $community, Course $course, ManageCourse $action): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
            'access_type' => ['required', 'in:free,inclusive,paid_once,paid_monthly,member_once'],
            'price'       => ['nullable', 'numeric', 'min:0', 'required_if:access_type,paid_once', 'required_if:access_type,paid_monthly'],
        ]);

        $action->update($course, $data, $request->file('cover_image'));

        return back()->with('success', 'Course updated!');
    }

    public function reorderCourses(Request $request, Community $community, ManageCourse $action): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

        $request->validate([
            'course_ids'   => ['required', 'array'],
            'course_ids.*' => ['required', 'integer', 'exists:courses,id'],
        ]);

        $action->reorder($community, $request->course_ids);

        return back()->with('success', 'Courses reordered!');
    }

    public function destroyCourse(Request $request, Community $community, Course $course, ManageCourse $action): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

        $action->destroy($course);

        return redirect()->route('communities.classroom', $community)->with('success', 'Course deleted!');
    }

    public function togglePublish(Request $request, Community $community, Course $course): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

        $course->update(['is_published' => ! $course->is_published]);

        $label = $course->is_published ? 'published' : 'set to draft';

        return back()->with('success', "Course {$label}!");
    }

    public function showCourse(Community $community, Course $course, GetCourseDetail $query, CourseAccessService $access): Response
    {
        $userId    = auth()->id();
        $canManage = $this->canManage(auth()->user(), $community);

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
    }

    public function storeModule(Request $request, Community $community, Course $course, ManageModule $action): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);
        $data = $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'is_free' => ['sometimes', 'boolean'],
        ]);
        $action->store($course, $data);

        return back()->with('success', 'Module added!');
    }

    public function updateModule(Request $request, Community $community, Course $course, CourseModule $module, ManageModule $action): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);
        $data = $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'is_free' => ['sometimes', 'boolean'],
        ]);
        $action->update($module, $data);

        return back()->with('success', 'Module updated!');
    }

    public function destroyModule(Request $request, Community $community, Course $course, CourseModule $module, ManageModule $action): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

        $action->destroy($module);

        return back()->with('success', 'Module deleted!');
    }

    public function destroyLesson(Request $request, Community $community, Course $course, CourseModule $module, CourseLesson $lesson): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

        $lesson->delete();

        return back()->with('success', 'Lesson deleted!');
    }

    public function storeLesson(Request $request, Community $community, Course $course, CourseModule $module, ManageLesson $action): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

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
    }

    public function completeLesson(Request $request, Community $community, Course $course, CourseLesson $lesson, CompleteLesson $action): RedirectResponse
    {
        $action->execute($request->user(), $lesson, $community->id);

        return back()->with('success', 'Lesson marked as complete!');
    }

    public function updateLesson(Request $request, Community $community, Course $course, CourseModule $module, CourseLesson $lesson, ManageLesson $action): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

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
    }

    public function uploadLessonImage(Request $request, Community $community, ManageLesson $action): JsonResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $url = $action->uploadImage($request->file('image'));

        return response()->json(['url' => $url]);
    }

    public function uploadLessonVideo(Request $request, Community $community, ManageLesson $action, PlanLimitService $planLimit): JsonResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

        $owner = $community->owner;
        if (! $planLimit->canUploadVideo($owner)) {
            return response()->json(['error' => 'Video uploads require a Pro plan.'], 403);
        }

        $maxKb = $planLimit->maxVideoSizeMb($owner->creatorPlan()) * 1024;

        $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-msvideo', "max:{$maxKb}"],
        ]);

        $url = $action->uploadVideo($request->file('video'));

        return response()->json(['url' => $url]);
    }

    public function streamLessonVideo(Request $request, Community $community, Course $course, CourseLesson $lesson, CourseAccessService $access): JsonResponse
    {
        $user      = $request->user();
        $canManage = $this->canManage($user, $community);

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

        $path = $lesson->video_path;

        // Handle legacy full S3 URLs stored before the migration to private keys
        if (str_starts_with($path, 'http')) {
            $bucket = config('filesystems.disks.s3.bucket');
            $parsed = parse_url($path);
            $path   = ltrim($parsed['path'] ?? '', '/');
        }

        // Generate a temporary signed URL (expires in 2 hours)
        $url = Storage::temporaryUrl($path, now()->addHours(2));

        return response()->json(['url' => $url]);
    }

    public function reorderLessons(Request $request, Community $community, Course $course, CourseModule $module, ManageLesson $action): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $community), 403);

        $request->validate([
            'lesson_ids'   => ['required', 'array'],
            'lesson_ids.*' => ['required', 'integer', 'exists:course_lessons,id'],
        ]);

        $action->reorder($module, $request->lesson_ids);

        return back()->with('success', 'Lessons reordered!');
    }
}
