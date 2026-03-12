<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Comment;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\LessonCompletion;
use App\Models\QuizAttempt;
use App\Services\BadgeService;
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
                'cover_image' => $course->cover_image,
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
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'cover_image'  => ['nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = asset('storage/' . $request->file('cover_image')->store('course-covers', 'public'));
        }

        $position = $community->courses()->max('position') + 1;
        $community->courses()->create(array_merge($data, ['position' => $position]));

        return back()->with('success', 'Course created!');
    }

    public function updateCourse(Request $request, Community $community, Course $course): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = asset('storage/' . $request->file('cover_image')->store('course-covers', 'public'));
        } else {
            unset($data['cover_image']);
        }

        $course->update($data);

        return back()->with('success', 'Course updated!');
    }

    public function showCourse(Community $community, Course $course): Response
    {
        $userId = auth()->id();
        $course->load('modules.lessons.quiz.questions.options');

        $lessonIds    = $course->modules->flatMap(fn ($m) => $m->lessons->pluck('id'));
        $completedIds = LessonCompletion::where('user_id', $userId)
            ->whereIn('lesson_id', $lessonIds)
            ->pluck('lesson_id')
            ->all();

        $total    = $lessonIds->count();
        $progress = $total > 0 ? round(count($completedIds) / $total * 100) : 0;

        // Load lesson comments keyed by lesson_id
        $lessonComments = Comment::whereIn('lesson_id', $lessonIds)
            ->whereNull('parent_id')
            ->with(['author:id,name,username,avatar', 'replies.author:id,name,username,avatar'])
            ->latest()
            ->get()
            ->groupBy('lesson_id')
            ->map(fn ($comments) => $comments->values());

        // Best quiz attempt per quiz (for this user)
        $quizAttempts = QuizAttempt::where('user_id', $userId)
            ->whereHas('quiz', fn ($q) => $q->whereIn('lesson_id', $lessonIds))
            ->get()
            ->groupBy('quiz_id')
            ->map(fn ($attempts) => $attempts->sortByDesc('score')->first());

        // Certificate if already issued
        $certificate = Certificate::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->first();

        return Inertia::render('Communities/Classroom/Show', [
            'community'      => $community,
            'course'         => $course,
            'completedIds'   => $completedIds,
            'progress'       => $progress,
            'lessonComments' => $lessonComments,
            'quizAttempts'   => $quizAttempts,
            'certificate'    => $certificate ? ['uuid' => $certificate->uuid] : null,
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

    public function updateModule(Request $request, Community $community, Course $course, CourseModule $module): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $module->update($data);

        return back()->with('success', 'Module updated!');
    }

    public function storeLesson(Request $request, Community $community, Course $course, CourseModule $module): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'content'   => ['nullable', 'string'],
            'video_url' => ['nullable', 'url', 'max:500'],
        ]);

        $position = $module->lessons()->max('position') + 1;
        $module->lessons()->create(array_merge($data, ['position' => $position]));

        return back()->with('success', 'Lesson added!');
    }

    public function completeLesson(Request $request, Community $community, Course $course, CourseLesson $lesson): RedirectResponse
    {
        $user = $request->user();

        LessonCompletion::firstOrCreate([
            'user_id'   => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        // Award badges
        app(BadgeService::class)->evaluate($user, $community->id);

        return back()->with('success', 'Lesson marked as complete!');
    }

    public function updateLesson(Request $request, Community $community, Course $course, CourseModule $module, CourseLesson $lesson): RedirectResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'content'   => ['nullable', 'string'],
            'video_url' => ['nullable', 'url', 'max:500'],
        ]);

        // If a URL is provided, clear any previously uploaded file
        if (!empty($data['video_url']) && $lesson->video_path) {
            Storage::disk('public')->delete($lesson->video_path);
            $data['video_path'] = null;
        }

        $lesson->update($data);

        return back()->with('success', 'Lesson updated!');
    }
}
