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
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Queries\Classroom\GetCourseDetail;
use App\Queries\Classroom\GetCourseList;
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
        ]);

        $action->update($course, $data, $request->file('cover_image'));

        return back()->with('success', 'Course updated!');
    }

    public function showCourse(Community $community, Course $course, GetCourseDetail $query): Response
    {
        $userId = auth()->id();
        $detail = $query->execute($course, $userId);

        $lessonIds = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
        $lessonComments = Comment::whereIn('lesson_id', $lessonIds)
            ->whereNull('parent_id')
            ->with(['author:id,name,username,avatar', 'replies.author:id,name,username,avatar'])
            ->latest()->get()->groupBy('lesson_id')->map(fn ($comments) => $comments->values());

        return Inertia::render('Communities/Classroom/Show', [
            'community'      => $community,
            'course'         => $course,
            'completedIds'   => $detail['completed_ids'],
            'progress'       => $detail['progress'],
            'lessonComments' => $lessonComments,
            'quizAttempts'   => $detail['quiz_attempts'],
            'certificate'    => $detail['certificate'] ? ['uuid' => $detail['certificate']->uuid] : null,
        ]);
    }

    public function storeModule(Request $request, Community $community, Course $course, ManageModule $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $action->store($course, $data);

        return back()->with('success', 'Module added!');
    }

    public function updateModule(Request $request, Community $community, Course $course, CourseModule $module, ManageModule $action): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
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
}
