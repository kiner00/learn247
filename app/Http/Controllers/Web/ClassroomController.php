<?php

namespace App\Http\Controllers\Web;

use App\Actions\Classroom\CompleteLesson;
use App\Actions\Classroom\ManageCourse;
use App\Actions\Classroom\ManageLesson;
use App\Actions\Classroom\ManageModule;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Subscription;
use App\Queries\Classroom\GetCourseDetail;
use App\Queries\Classroom\GetCourseList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Community $community, GetCourseList $query): Response
    {
        $userId = auth()->id();
        $community->loadCount('members');
        $courses   = $query->execute($community, $userId);
        $affiliate = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;

        return Inertia::render('Communities/Classroom/Index', compact('community', 'courses', 'affiliate'));
    }

    public function storeCourse(Request $request, Community $community, ManageCourse $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
            'access_type' => ['required', 'in:free,inclusive,paid_once,paid_monthly'],
            'price'       => ['nullable', 'numeric', 'min:0', 'required_if:access_type,paid_once', 'required_if:access_type,paid_monthly'],
        ]);

        $action->store($community, $data, $request->file('cover_image'));

        return back()->with('success', 'Course created!');
    }

    public function updateCourse(Request $request, Community $community, Course $course, ManageCourse $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_image' => ['nullable', 'image', 'max:10240'],
            'access_type' => ['required', 'in:free,inclusive,paid_once,paid_monthly'],
            'price'       => ['nullable', 'numeric', 'min:0', 'required_if:access_type,paid_once', 'required_if:access_type,paid_monthly'],
        ]);

        $action->update($course, $data, $request->file('cover_image'));

        return back()->with('success', 'Course updated!');
    }

    public function destroyCourse(Request $request, Community $community, Course $course, ManageCourse $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $action->destroy($course);

        return redirect()->route('communities.classroom', $community)->with('success', 'Course deleted!');
    }

    public function showCourse(Community $community, Course $course, GetCourseDetail $query): Response
    {
        $userId    = auth()->id();
        $hasAccess = $this->userHasAccessToCourse(auth()->user(), $community, $course);
        $detail    = $query->execute($course, $userId, $hasAccess);

        $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
        $lessonComments = Comment::whereIn('lesson_id', $lessonIds)
            ->whereNull('parent_id')
            ->with(['author:id,name,username,avatar', 'replies.author:id,name,username,avatar'])
            ->latest()->get()->groupBy('lesson_id')->map(fn ($comments) => $comments->values());

        $enrollment = $userId
            ? CourseEnrollment::where('user_id', $userId)->where('course_id', $course->id)->orderByDesc('id')->first()
            : null;

        return Inertia::render('Communities/Classroom/Show', [
            'community'      => $community,
            'course'         => $course->append([]),
            'hasAccess'      => $hasAccess,
            'enrollment'     => $enrollment ? ['status' => $enrollment->status] : null,
            'completedIds'   => $detail['completed_ids'],
            'progress'       => $detail['progress'],
            'lessonComments' => $lessonComments,
            'quizAttempts'   => $detail['quiz_attempts'],
            'certificate'    => $detail['certificate'] ? ['uuid' => $detail['certificate']->uuid] : null,
        ]);
    }

    private function userHasAccessToCourse(?\App\Models\User $user, Community $community, Course $course): bool
    {
        if ($course->access_type === Course::ACCESS_FREE) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->id === $community->owner_id) {
            return true;
        }

        if ($course->access_type === Course::ACCESS_INCLUSIVE) {
            return Subscription::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
        }

        if (in_array($course->access_type, [Course::ACCESS_PAID_ONCE, Course::ACCESS_PAID_MONTHLY])) {
            return CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('status', CourseEnrollment::STATUS_PAID)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
        }

        return false;
    }

    public function storeModule(Request $request, Community $community, Course $course, ManageModule $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);
        $data = $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'is_free' => ['sometimes', 'boolean'],
        ]);
        $action->store($course, $data);

        return back()->with('success', 'Module added!');
    }

    public function updateModule(Request $request, Community $community, Course $course, CourseModule $module, ManageModule $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);
        $data = $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'is_free' => ['sometimes', 'boolean'],
        ]);
        $action->update($module, $data);

        return back()->with('success', 'Module updated!');
    }

    public function storeLesson(Request $request, Community $community, Course $course, CourseModule $module, ManageLesson $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'content'   => ['nullable', 'string'],
            'video_url' => ['nullable', 'url', 'max:500'],
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
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'content'   => ['nullable', 'string'],
            'video_url' => ['nullable', 'url', 'max:500'],
        ]);

        $action->update($lesson, $data);

        return back()->with('success', 'Lesson updated!');
    }

    public function uploadLessonImage(Request $request, Community $community, ManageLesson $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $url = $action->uploadImage($request->file('image'));

        return response()->json(['url' => $url]);
    }

    public function reorderLessons(Request $request, Community $community, Course $course, CourseModule $module, ManageLesson $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $request->validate([
            'lesson_ids'   => ['required', 'array'],
            'lesson_ids.*' => ['required', 'integer', 'exists:course_lessons,id'],
        ]);

        $action->reorder($module, $request->lesson_ids);

        return back()->with('success', 'Lessons reordered!');
    }
}
