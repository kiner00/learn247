<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\LessonCompletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Community $community): Response
    {
        $userId  = auth()->id();
        $community->loadCount('members');
        $courses = $community->courses()->with('modules.lessons')->get();

        $courses = $courses->map(function ($course) use ($userId) {
            $lessonIds   = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
            $total       = $lessonIds->count();
            $completed   = $total > 0
                ? LessonCompletion::where('user_id', $userId)->whereIn('lesson_id', $lessonIds)->count()
                : 0;

            return [
                'id'          => $course->id,
                'title'       => $course->title,
                'description' => $course->description,
                'position'    => $course->position,
                'total'       => $total,
                'completed'   => $completed,
            ];
        });

        $affiliate = $userId ? $community->affiliates()->where('user_id', $userId)->first() : null;

        return Inertia::render('Communities/Classroom/Index', compact('community', 'courses', 'affiliate'));
    }

    public function storeCourse(Request $request, Community $community): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $position = $community->courses()->max('position') + 1;
        $community->courses()->create(array_merge($data, ['position' => $position]));

        return back()->with('success', 'Course created!');
    }

    public function showCourse(Community $community, Course $course): Response
    {
        $userId = auth()->id();
        $course->load('modules.lessons');

        $lessonIds     = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
        $completedIds  = LessonCompletion::where('user_id', $userId)
            ->whereIn('lesson_id', $lessonIds)
            ->pluck('lesson_id')
            ->all();

        $total    = $lessonIds->count();
        $progress = $total > 0 ? round(count($completedIds) / $total * 100) : 0;

        return Inertia::render('Communities/Classroom/Show', [
            'community'    => $community,
            'course'       => $course,
            'completedIds' => $completedIds,
            'progress'     => $progress,
        ]);
    }

    public function storeModule(Request $request, Community $community, Course $course): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data     = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $position = $course->modules()->max('position') + 1;
        $course->modules()->create(array_merge($data, ['position' => $position]));

        return back()->with('success', 'Module added!');
    }

    public function storeLesson(Request $request, Community $community, Course $course, CourseModule $module): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'content'     => ['nullable', 'string'],
            'video_url'   => ['nullable', 'url', 'max:500'],
            'video_file'  => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime', 'max:512000'],
        ]);

        if ($request->hasFile('video_file')) {
            $data['video_path'] = $request->file('video_file')->store('lesson-videos', 'public');
            $data['video_url']  = null;
        }
        unset($data['video_file']);

        $position = $module->lessons()->max('position') + 1;
        $module->lessons()->create(array_merge($data, ['position' => $position]));

        return back()->with('success', 'Lesson added!');
    }

    public function completeLesson(Request $request, Community $community, Course $course, CourseLesson $lesson): RedirectResponse
    {
        LessonCompletion::firstOrCreate([
            'user_id'   => $request->user()->id,
            'lesson_id' => $lesson->id,
        ]);

        return back()->with('success', 'Lesson marked as complete!');
    }

    public function updateLesson(Request $request, Community $community, Course $course, CourseModule $module, CourseLesson $lesson): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'content'    => ['nullable', 'string'],
            'video_url'  => ['nullable', 'url', 'max:500'],
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime', 'max:512000'],
        ]);

        if ($request->hasFile('video_file')) {
            // Delete old uploaded file if present
            if ($lesson->video_path) {
                Storage::disk('public')->delete($lesson->video_path);
            }
            $data['video_path'] = $request->file('video_file')->store('lesson-videos', 'public');
            $data['video_url']  = null;
        } elseif (!empty($data['video_url'])) {
            // Switched to URL — clear any uploaded file
            if ($lesson->video_path) {
                Storage::disk('public')->delete($lesson->video_path);
            }
            $data['video_path'] = null;
        }
        unset($data['video_file']);

        $lesson->update($data);

        return back()->with('success', 'Lesson updated!');
    }
}
