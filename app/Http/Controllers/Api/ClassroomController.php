<?php

namespace App\Http\Controllers\Api;

use App\Actions\Classroom\CompleteLesson;
use App\Actions\Classroom\ManageCourse;
use App\Actions\Classroom\ManageLesson;
use App\Actions\Classroom\ManageModule;
use App\Actions\Classroom\SubmitQuiz;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Quiz;
use App\Models\Subscription;
use App\Queries\Classroom\GetCourseDetail;
use App\Queries\Classroom\GetCourseList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function courses(Request $request, Community $community, GetCourseList $query): JsonResponse
    {
        $this->requireMembership($request, $community);

        return response()->json(['courses' => $query->execute($community, $request->user()->id)]);
    }

    public function course(Request $request, Community $community, Course $course, GetCourseDetail $query): JsonResponse
    {
        $this->requireMembership($request, $community);

        $detail       = $query->execute($course, $request->user()->id);
        $completedIds = $detail['completed_ids'];
        $quizAttempts = $detail['quiz_attempts']->map(fn ($a) => [
            'score'  => $a->score,
            'passed' => $a->passed,
        ]);

        $modules = $course->modules->map(fn ($module) => [
            'id'       => $module->id,
            'title'    => $module->title,
            'position' => $module->position,
            'lessons'  => $module->lessons->map(fn ($lesson) => [
                'id'        => $lesson->id,
                'title'     => $lesson->title,
                'position'  => $lesson->position,
                'video_url' => $lesson->video_url,
                'content'   => $lesson->content,
                'completed' => in_array($lesson->id, $completedIds),
                'quiz'      => $lesson->quiz ? [
                    'id'           => $lesson->quiz->id,
                    'title'        => $lesson->quiz->title,
                    'pass_score'   => $lesson->quiz->pass_score,
                    'questions'    => $lesson->quiz->questions->map(fn ($q) => [
                        'id'       => $q->id,
                        'question' => $q->question,
                        'type'     => $q->type,
                        'options'  => $q->options->map(fn ($o) => [
                            'id'    => $o->id,
                            'label' => $o->label,
                        ]),
                    ]),
                    'best_attempt' => $quizAttempts[$lesson->quiz->id] ?? null,
                ] : null,
            ])->values(),
        ])->values();

        return response()->json([
            'course'      => [
                'id'          => $course->id,
                'title'       => $course->title,
                'description' => $course->description,
            ],
            'modules'     => $modules,
            'progress'    => $detail['progress'],
            'certificate' => $detail['certificate'] ? ['uuid' => $detail['certificate']->uuid] : null,
        ]);
    }

    public function completeLesson(Request $request, Community $community, Course $course, CourseLesson $lesson, CompleteLesson $action): JsonResponse
    {
        $action->execute($request->user(), $lesson, $community->id);

        return response()->json(['message' => 'Lesson marked as complete!']);
    }

    public function submitQuiz(Request $request, Community $community, Course $course, CourseLesson $lesson, Quiz $quiz, SubmitQuiz $action): JsonResponse
    {
        $request->validate([
            'answers'   => ['required', 'array'],
            'answers.*' => ['required', 'integer'],
        ]);

        $result = $action->execute($request->user(), $quiz, $request->answers, $community->id);

        return response()->json([
            'score'   => $result['score'],
            'passed'  => $result['passed'],
            'total'   => $result['total'],
            'correct' => $result['correct'],
        ]);
    }

    public function storeCourse(Request $request, Community $community, ManageCourse $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $course = $action->store($community, $data, $request->file('cover_image'));

        return response()->json(['message' => 'Course created.', 'course_id' => $course->id], 201);
    }

    public function updateCourse(Request $request, Community $community, Course $course, ManageCourse $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $action->update($course, $data, $request->file('cover_image'));

        return response()->json(['message' => 'Course updated.']);
    }

    public function storeModule(Request $request, Community $community, Course $course, ManageModule $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $module = $action->store($course, $data);

        return response()->json(['message' => 'Module added.', 'module_id' => $module->id], 201);
    }

    public function updateModule(Request $request, Community $community, Course $course, CourseModule $module, ManageModule $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $action->update($module, $data);

        return response()->json(['message' => 'Module updated.']);
    }

    public function storeLesson(Request $request, Community $community, Course $course, CourseModule $module, ManageLesson $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'content'   => ['nullable', 'string'],
            'video_url' => ['nullable', 'url', 'max:500'],
        ]);

        $lesson = $action->store($module, $data);

        return response()->json(['message' => 'Lesson added.', 'lesson_id' => $lesson->id], 201);
    }

    public function updateLesson(Request $request, Community $community, Course $course, CourseModule $module, CourseLesson $lesson, ManageLesson $action): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $data = $request->validate([
            'content'   => ['nullable', 'string'],
            'video_url' => ['nullable', 'url', 'max:500'],
        ]);

        $action->update($lesson, $data);

        return response()->json(['message' => 'Lesson updated.']);
    }

    private function requireMembership(Request $request, Community $community): void
    {
        $user = $request->user();

        if ($community->owner_id === $user->id) {
            return;
        }

        if ($community->isFree()) {
            abort_unless(
                CommunityMember::where('community_id', $community->id)->where('user_id', $user->id)->exists(),
                403,
                'You must be a member of this community.'
            );
            return;
        }

        abort_unless(
            Subscription::where('community_id', $community->id)
                ->where('user_id', $user->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists(),
            403,
            'An active membership is required.'
        );
    }
}
